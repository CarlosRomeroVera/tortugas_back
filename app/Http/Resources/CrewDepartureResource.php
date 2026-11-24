<?php

namespace App\Http\Resources;

use App\Enums\AssetKind;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Departure;
use App\Models\ServiceStage;
use App\Models\Staff;
use App\Support\LocalTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Salida vista por su tripulación (app del capitán o chofer).
 * Con withManifest() agrega las etapas y la lista de pasajeros.
 *
 * @mixin Departure
 */
class CrewDepartureResource extends JsonResource
{
    private bool $manifest = false;

    public function withManifest(): static
    {
        $this->manifest = true;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $tz = LocalTime::zone();
        $bookings = $this->bookings->where('status', '!=', BookingStatus::Cancelled);
        $me = $this->crew->firstWhere('user_id', $request->user()->getKey());

        $base = [
            'id' => $this->id,
            'code' => $this->code,
            'status' => $this->status,
            'service' => [
                'id' => $this->service->id,
                'slug' => $this->service->slug,
                'name' => $this->service->name,
                'shared' => $this->service->is_shared,
            ],
            'asset' => [
                'id' => $this->asset->id,
                'name' => $this->asset->name,
                'kind' => $this->asset->kind,
                'registration' => $this->asset->registration,
                'capacity' => $this->asset->capacity,
                'reading' => [
                    'unit' => $this->asset->kind === AssetKind::Boat ? 'hours' : 'km',
                    'current' => $this->asset->currentReading(),
                ],
            ],
            'starts_at' => $this->starts_at->toIso8601String(),
            'ends_at' => $this->ends_at->toIso8601String(),
            'local_date' => $this->starts_at->tz($tz)->toDateString(),
            'local_time' => $this->starts_at->tz($tz)->format('H:i'),
            'local_end_time' => $this->ends_at->tz($tz)->format('H:i'),
            'departed_at' => $this->departed_at?->toIso8601String(),
            'returned_at' => $this->returned_at?->toIso8601String(),
            'my_role' => $me?->pivot->role,
            'crew' => $this->crew->map(fn (Staff $s) => ['name' => $s->name, 'role' => $s->pivot->role, 'phone' => $s->phone])->values(),
            'capacity' => $this->capacity,
            'bookings_count' => $bookings->count(),
            'pax' => [
                'booked' => (int) $bookings->where('status', '!=', BookingStatus::NoShow)->sum('pax'),
                'checked_in' => (int) $bookings->whereNotNull('checked_in_at')->sum('pax'),
                'no_show' => (int) $bookings->where('status', BookingStatus::NoShow)->sum('pax'),
            ],
            'current_stage' => $this->currentStage ? [
                'id' => $this->currentStage->id,
                'position' => $this->currentStage->position,
                'name' => $this->currentStage->name,
            ] : null,
            'stages_total' => $this->service->stages->count(),
            'notes' => $this->notes,
        ];

        if (! $this->manifest) {
            return $base;
        }

        $reached = $this->fieldEvents->keyBy('service_stage_id');

        return $base + [
            'stages' => $this->service->stages->map(fn (ServiceStage $st) => [
                'id' => $st->id,
                'position' => $st->position,
                'name' => $st->name,
                'requires_reading' => $st->requires_reading,
                'reached_at' => $reached->get($st->id)?->occurred_at?->toIso8601String(),
                'done' => $st->position <= ($this->currentStage?->position ?? 0),
            ])->values(),
            'manifest' => $bookings
                ->sortBy(fn (Booking $b) => [$b->pickupLocation?->name ?? '', $b->customer->name])
                ->map(fn (Booking $b) => $this->manifestRow($b, $tz))
                ->values(),
        ];
    }

    private function manifestRow(Booking $b, string $tz): array
    {
        return [
            'id' => $b->id,
            'code' => $b->code,
            'status' => $b->status,
            'adults' => $b->adults,
            'children' => $b->children,
            'pax' => $b->pax,
            'customer' => [
                'name' => $b->customer->name,
                'phone' => $b->customer->phone,
                'country' => $b->customer->country,
            ],
            'cruise_ship' => $b->cruise_ship,
            'all_aboard_at' => $b->all_aboard_at?->toIso8601String(),
            'all_aboard_local_time' => $b->all_aboard_at?->tz($tz)->format('H:i'),
            'pickup' => $b->pickupLocation ? [
                'id' => $b->pickupLocation->id,
                'name' => $b->pickupLocation->name,
                'type' => $b->pickupLocation->type,
                'address' => $b->pickupLocation->address,
                'latitude' => $b->pickupLocation->latitude !== null ? (float) $b->pickupLocation->latitude : null,
                'longitude' => $b->pickupLocation->longitude !== null ? (float) $b->pickupLocation->longitude : null,
                'notes' => $b->pickupLocation->pickup_notes,
            ] : null,
            'checked_in_at' => $b->checked_in_at?->toIso8601String(),
            'currency' => $b->currency,
            'balance_cents' => $b->balanceCents(),
            'notes' => $b->notes,
        ];
    }
}
