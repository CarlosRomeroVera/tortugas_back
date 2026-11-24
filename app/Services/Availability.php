<?php

namespace App\Services;

use App\Enums\DepartureStatus;
use App\Models\Allocation;
use App\Models\Asset;
use App\Models\Departure;
use App\Models\Service;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Horarios que se pueden vender.
 * - Compartido: salidas programadas del día con lugares suficientes.
 * - Privado: horas de inicio en las que alguna unidad con capacidad está libre
 *   durante toda la duración del servicio más su margen.
 *
 * Es solo una consulta: la garantía final la dan CreateBooking y AllocateAsset.
 */
final class Availability
{
    /** @return Collection<int, array<string, mixed>> */
    public function for(Service $service, string $date, int $pax, ?CarbonImmutable $now = null): Collection
    {
        $now ??= CarbonImmutable::now();

        return $service->is_shared
            ? $this->sharedSlots($service, $date, $pax, $now)
            : $this->privateSlots($service, $date, $pax, $now);
    }

    private function sharedSlots(Service $service, string $date, int $pax, CarbonImmutable $now): Collection
    {
        [$from, $to] = LocalTime::dayBounds($date);

        return Departure::query()
            ->with('asset:id,name')
            ->withSum(['bookings as seats_taken' => fn ($q) => $q->occupyingSeats()], 'pax')
            ->where('service_id', $service->getKey())
            ->where('status', DepartureStatus::Scheduled)
            ->where('starts_at', '>=', max($from, $now))
            ->where('starts_at', '<', $to)
            ->orderBy('starts_at')
            ->get()
            ->map(fn (Departure $d) => [
                'departure_id' => $d->id,
                'starts_at' => $d->starts_at->toIso8601String(),
                'local_time' => $d->starts_at->tz(LocalTime::zone())->format('H:i'),
                'asset' => $d->asset->name,
                'capacity' => $d->capacity,
                'seats_left' => $left = max(0, $d->capacity - (int) $d->seats_taken),
                'available' => $left >= $pax,
            ])
            ->values();
    }

    private function privateSlots(Service $service, string $date, int $pax, CarbonImmutable $now): Collection
    {
        $hours = config('tortugas.operating_hours');
        $step = config('tortugas.slot_step_minutes');
        $dayStart = LocalTime::toUtc($date, $hours['start']);
        $dayEnd = LocalTime::toUtc($date, $hours['end']);
        $length = $service->duration_minutes + $service->buffer_minutes;

        $assets = $service->eligibleAssets()
            ->where('capacity', '>=', $pax)
            ->orderBy('capacity')
            ->get();

        $busy = Allocation::query()
            ->active()
            ->whereIn('asset_id', $assets->modelKeys())
            ->overlapping($dayStart, $dayEnd)
            ->get(['asset_id', 'starts_at', 'ends_at'])
            ->groupBy('asset_id');

        $slots = collect();
        for ($start = $dayStart; $start->addMinutes($service->duration_minutes) <= $dayEnd; $start = $start->addMinutes($step)) {
            if ($start <= $now) {
                continue;
            }
            $end = $start->addMinutes($length);

            $free = $assets->filter(fn (Asset $a) => ($busy[$a->id] ?? collect())
                ->doesntContain(fn ($b) => $b->starts_at < $end && $b->ends_at > $start));

            if ($free->isNotEmpty()) {
                $slots->push([
                    'starts_at' => $start->toIso8601String(),
                    'local_time' => $start->tz(LocalTime::zone())->format('H:i'),
                    'assets' => $free->map(fn (Asset $a) => [
                        'id' => $a->id, 'name' => $a->name, 'capacity' => $a->capacity,
                    ])->values(),
                    'available' => true,
                ]);
            }
        }

        return $slots;
    }
}
