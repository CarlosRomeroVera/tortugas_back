<?php

namespace App\Actions\Departures;

use App\Actions\Allocations\AllocateAsset;
use App\Enums\AllocationKind;
use App\Enums\DepartureStatus;
use App\Exceptions\InvalidBooking;
use App\Models\Asset;
use App\Models\Departure;
use App\Models\Service;
use App\Support\Codes;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/** Crea una salida y ocupa su embarcación o vehículo (duración + margen entre salidas). */
final class ScheduleDeparture
{
    public function __construct(private AllocateAsset $allocate) {}

    public function handle(Service $service, Asset $asset, DateTimeInterface $startsAt, ?int $capacity = null): Departure
    {
        if ($asset->kind !== $service->asset_kind) {
            throw new InvalidBooking("{$service->name} necesita un recurso de tipo {$service->asset_kind->getLabel()}.");
        }

        $start = CarbonImmutable::instance($startsAt)->utc();
        $end = $start->addMinutes($service->duration_minutes);
        $capacity = min($capacity ?? $service->default_capacity ?? $asset->capacity, $asset->capacity);

        return DB::transaction(function () use ($service, $asset, $start, $end, $capacity) {
            $departure = Departure::create([
                'code' => Codes::make('SAL', 4, $start->tz(config('tortugas.display_timezone'))->format('Ymd')),
                'service_id' => $service->getKey(),
                'asset_id' => $asset->getKey(),
                'starts_at' => $start,
                'ends_at' => $end,
                'capacity' => $capacity,
                'status' => DepartureStatus::Scheduled,
            ]);

            $this->allocate->handle(
                $asset,
                $start,
                $end->addMinutes($service->buffer_minutes),
                AllocationKind::Departure,
                $departure,
            );

            return $departure;
        }, attempts: 3);
    }
}
