<?php

namespace App\Actions\Field;

use App\Enums\AssetKind;
use App\Enums\BookingStatus;
use App\Enums\DepartureStatus;
use App\Enums\FaultStatus;
use App\Enums\FieldEventType;
use App\Models\Asset;
use App\Models\Booking;
use App\Models\Departure;
use App\Models\FaultReport;
use App\Models\FieldEvent;
use App\Models\ServiceStage;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Aplica un evento que llega de la app del capitán o del chofer.
 *
 * - Idempotente: el client_uuid se guarda con el evento; si llega otra vez, no se repite.
 * - Las etapas solo avanzan. Un evento atrasado (que llega tarde por falta de señal)
 *   se guarda pero no regresa la salida a una etapa anterior.
 * - Cada evento corre en su transacción y bloquea la fila de la salida, igual que
 *   CreateBooking, para que capitán y guía no se pisen.
 *
 * @phpstan-type Result array{client_uuid: string, status: string, reason?: string, message?: ?string}
 */
final class RecordFieldEvent
{
    public const APPLIED = 'applied';

    public const IGNORED = 'ignored';

    public const DUPLICATE = 'duplicate';

    public const REJECTED = 'rejected';

    /** @return Result */
    public function handle(User $user, FieldEventData $e): array
    {
        if ($previous = FieldEvent::query()->where('client_uuid', $e->clientUuid)->first()) {
            return $this->result($e, self::DUPLICATE, $previous->message);
        }

        try {
            $event = DB::transaction(fn () => match ($e->type) {
                FieldEventType::Stage => $this->stage($user, $e),
                FieldEventType::CheckIn => $this->checkIn($user, $e),
                FieldEventType::CheckInUndo => $this->undoCheckIn($user, $e),
                FieldEventType::NoShow => $this->noShow($user, $e),
                FieldEventType::Fault => $this->fault($user, $e),
            }, attempts: 3);
        } catch (FieldEventRejected $x) {
            return $this->result($e, self::REJECTED, $x->getMessage(), $x->reason);
        } catch (UniqueConstraintViolationException) {
            // El mismo evento entró al mismo tiempo por otra petición.
            return $this->result($e, self::DUPLICATE);
        }

        return $this->result($e, $event->outcome, $event->message);
    }

    private function stage(User $user, FieldEventData $e): FieldEvent
    {
        $departure = $this->lockDeparture($user, $e->departureId);

        $stage = ServiceStage::query()
            ->where('service_id', $departure->service_id)
            ->find($e->stageId)
            ?? throw new FieldEventRejected('not_found', 'La etapa no pertenece al servicio de esta salida.');

        $refs = ['departure_id' => $departure->getKey(), 'service_stage_id' => $stage->getKey(), 'asset_id' => $departure->asset_id];

        if ($stage->position <= ($departure->currentStage?->position ?? 0)) {
            return $this->log($user, $e, FieldEvent::IGNORED, 'Ya se había registrado esta etapa o una posterior.', $refs);
        }

        if ($stage->requires_reading && $e->reading === null) {
            throw new FieldEventRejected('reading_required', $departure->asset->kind === AssetKind::Boat
                ? 'Captura el horómetro para registrar esta etapa.'
                : 'Captura el odómetro para registrar esta etapa.');
        }

        if ($e->reading !== null) {
            $this->recordReading($departure->asset, $e->reading);
        }

        $isLast = $stage->position >= ServiceStage::query()->where('service_id', $departure->service_id)->max('position');

        $departure->fill(['current_stage_id' => $stage->getKey()]);
        if ($departure->status === DepartureStatus::Scheduled) {
            $departure->fill(['status' => DepartureStatus::InProgress, 'departed_at' => $e->occurredAt]);
        }
        if ($isLast) {
            $departure->fill([
                'status' => DepartureStatus::Completed,
                'departed_at' => $departure->departed_at ?? $e->occurredAt,
                'returned_at' => $e->occurredAt,
            ]);

            // Quien abordó completó su tour; los demás los revisa coordinación.
            $departure->bookings()
                ->whereNotNull('checked_in_at')
                ->whereIn('status', [BookingStatus::Pending, BookingStatus::Confirmed])
                ->get()
                ->each(fn (Booking $b) => $b->update(['status' => BookingStatus::Completed]));
        }
        $departure->save();

        return $this->log($user, $e, FieldEvent::APPLIED, null, $refs);
    }

    private function checkIn(User $user, FieldEventData $e): FieldEvent
    {
        [$departure, $booking] = $this->lockBooking($user, $e->bookingId);
        $refs = ['departure_id' => $departure->getKey(), 'booking_id' => $booking->getKey()];

        if ($booking->status === BookingStatus::Cancelled) {
            throw new FieldEventRejected('booking_cancelled', "La reserva {$booking->code} está cancelada.");
        }
        if ($booking->checked_in_at) {
            return $this->log($user, $e, FieldEvent::IGNORED, 'Ya estaba registrado.', $refs);
        }

        // Si alguien deshizo el registro después de este momento, gana lo más reciente.
        $undoneLater = FieldEvent::query()
            ->where('booking_id', $booking->getKey())
            ->where('type', FieldEventType::CheckInUndo)
            ->where('occurred_at', '>', $e->occurredAt)
            ->exists();
        if ($undoneLater) {
            return $this->log($user, $e, FieldEvent::IGNORED, 'Se deshizo después desde otro dispositivo.', $refs);
        }

        $status = match (true) {
            $departure->status === DepartureStatus::Completed => BookingStatus::Completed,
            $booking->status === BookingStatus::NoShow => $booking->paidCents() > 0 ? BookingStatus::Confirmed : BookingStatus::Pending,
            default => $booking->status,
        };

        $booking->update(['checked_in_at' => $e->occurredAt, 'checked_in_by' => $user->getKey(), 'status' => $status]);

        return $this->log($user, $e, FieldEvent::APPLIED, null, $refs);
    }

    private function undoCheckIn(User $user, FieldEventData $e): FieldEvent
    {
        [$departure, $booking] = $this->lockBooking($user, $e->bookingId);
        $refs = ['departure_id' => $departure->getKey(), 'booking_id' => $booking->getKey()];

        if ($departure->status === DepartureStatus::Completed) {
            throw new FieldEventRejected('departure_closed', "La salida {$departure->code} ya terminó; pide a coordinación que lo corrija.");
        }
        if (! $booking->checked_in_at || $booking->checked_in_at->greaterThan($e->occurredAt)) {
            // Se guarda igual: si el registro llega después, este evento lo anula.
            return $this->log($user, $e, FieldEvent::IGNORED, 'No estaba registrado.', $refs);
        }

        $booking->update(['checked_in_at' => null, 'checked_in_by' => null]);

        return $this->log($user, $e, FieldEvent::APPLIED, null, $refs);
    }

    private function noShow(User $user, FieldEventData $e): FieldEvent
    {
        [$departure, $booking] = $this->lockBooking($user, $e->bookingId);
        $refs = ['departure_id' => $departure->getKey(), 'booking_id' => $booking->getKey()];

        if ($booking->status === BookingStatus::Cancelled) {
            throw new FieldEventRejected('booking_cancelled', "La reserva {$booking->code} está cancelada.");
        }
        if ($booking->checked_in_at) {
            throw new FieldEventRejected('checked_in', 'El pasajero ya está registrado. Deshaz el registro antes de marcarlo como no presentado.');
        }
        if ($booking->status === BookingStatus::NoShow) {
            return $this->log($user, $e, FieldEvent::IGNORED, 'Ya estaba marcado como no presentado.', $refs);
        }

        $booking->update(['status' => BookingStatus::NoShow]);

        return $this->log($user, $e, FieldEvent::APPLIED, null, $refs);
    }

    private function fault(User $user, FieldEventData $e): FieldEvent
    {
        $asset = Asset::query()->find($e->assetId)
            ?? throw new FieldEventRejected('not_found', 'La unidad no existe.');

        $departure = $e->departureId ? $this->lockDeparture($user, $e->departureId, allowClosed: true) : null;

        $report = FaultReport::create([
            'asset_id' => $asset->getKey(),
            'departure_id' => $departure?->getKey(),
            'reported_by' => $user->getKey(),
            'severity' => $e->severity,
            'status' => FaultStatus::Open,
            'description' => $e->description,
            'reported_at' => $e->occurredAt,
        ]);

        return $this->log($user, $e, FieldEvent::APPLIED, null, [
            'asset_id' => $asset->getKey(),
            'departure_id' => $departure?->getKey(),
            'fault_report_id' => $report->getKey(),
        ]);
    }

    /** La salida, bloqueada, solo si el usuario va en su tripulación. */
    private function lockDeparture(User $user, ?string $departureId, bool $allowClosed = false): Departure
    {
        $departure = Departure::query()->crewedBy($user)->whereKey($departureId)->lockForUpdate()->first();

        if (! $departure) {
            throw Departure::query()->whereKey($departureId)->exists()
                ? new FieldEventRejected('not_crew', 'No estás asignado a esta salida.')
                : new FieldEventRejected('not_found', 'La salida no existe.');
        }

        if (! $allowClosed && $departure->status === DepartureStatus::Cancelled) {
            throw new FieldEventRejected('departure_cancelled', "La salida {$departure->code} fue cancelada.");
        }

        return $departure;
    }

    /** @return array{Departure, Booking} Se bloquea primero la salida y luego la reserva, como en CreateBooking. */
    private function lockBooking(User $user, ?string $bookingId): array
    {
        $departureId = Booking::query()->whereKey($bookingId)->value('departure_id')
            ?? throw new FieldEventRejected('not_found', 'La reserva no existe.');

        $departure = $this->lockDeparture($user, $departureId);
        $booking = Booking::query()->whereKey($bookingId)->lockForUpdate()->firstOrFail();

        return [$departure, $booking];
    }

    private function recordReading(Asset $asset, float $reading): void
    {
        $current = $asset->currentReading();

        if ($current !== null && $reading < $current) {
            throw new FieldEventRejected('invalid_reading', sprintf(
                'La lectura %s es menor que la última registrada de %s (%s).',
                number_format($reading, 1), $asset->name, number_format($current, 1),
            ));
        }

        $asset->update([
            $asset->readingField() => $asset->kind === AssetKind::Boat ? $reading : (int) round($reading),
        ]);
    }

    private function log(User $user, FieldEventData $e, string $outcome, ?string $message, array $refs): FieldEvent
    {
        return FieldEvent::create($refs + [
            'client_uuid' => $e->clientUuid,
            'type' => $e->type,
            'outcome' => $outcome,
            'message' => $message,
            'user_id' => $user->getKey(),
            'reading' => $e->reading,
            'latitude' => $e->latitude,
            'longitude' => $e->longitude,
            'occurred_at' => $e->occurredAt,
            'received_at' => now(),
        ]);
    }

    /** @return Result */
    private function result(FieldEventData $e, string $status, ?string $message = null, ?string $reason = null): array
    {
        return array_filter([
            'client_uuid' => $e->clientUuid,
            'status' => $status,
            'reason' => $reason,
            'message' => $message,
        ], fn ($v) => $v !== null);
    }
}
