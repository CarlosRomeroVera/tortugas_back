<?php

namespace App\Filament\Resources\MaintenanceOrders\Pages;

use App\Actions\Maintenance\ScheduleMaintenance;
use App\Enums\MaintenanceType;
use App\Filament\Resources\MaintenanceOrders\MaintenanceOrderResource;
use App\Filament\Support\Domain;
use App\Models\Asset;
use Carbon\CarbonImmutable;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateMaintenanceOrder extends CreateRecord
{
    protected static string $resource = MaintenanceOrderResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return Domain::run(fn () => app(ScheduleMaintenance::class)->handle(
            Asset::findOrFail($data['asset_id']),
            $data['type'] instanceof MaintenanceType ? $data['type'] : MaintenanceType::from($data['type']),
            $data['title'],
            CarbonImmutable::parse($data['starts_at'])->utc(),
            CarbonImmutable::parse($data['ends_at'])->utc(),
            $data['description'] ?? null,
        ));
    }
}
