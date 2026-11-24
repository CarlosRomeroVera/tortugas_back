<?php

namespace App\Actions\Maintenance;

use App\Actions\Allocations\AllocateAsset;
use App\Enums\AllocationKind;
use App\Enums\MaintenanceStatus;
use App\Enums\MaintenanceType;
use App\Models\Asset;
use App\Models\MaintenanceOrder;
use App\Support\Codes;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/** Crea la orden y bloquea la agenda del recurso para que ventas no lo venda. */
final class ScheduleMaintenance
{
    public function __construct(private AllocateAsset $allocate) {}

    public function handle(
        Asset $asset,
        MaintenanceType $type,
        string $title,
        DateTimeInterface $startsAt,
        DateTimeInterface $endsAt,
        ?string $description = null,
    ): MaintenanceOrder {
        return DB::transaction(function () use ($asset, $type, $title, $startsAt, $endsAt, $description) {
            $order = MaintenanceOrder::create([
                'code' => Codes::make('OT'),
                'asset_id' => $asset->getKey(),
                'type' => $type,
                'status' => MaintenanceStatus::Scheduled,
                'title' => $title,
                'description' => $description,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
            ]);

            $this->allocate->handle($asset, $startsAt, $endsAt, AllocationKind::Maintenance, $order, $title);

            return $order;
        }, attempts: 3);
    }
}
