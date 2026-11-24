<?php

namespace App\Filament\Resources\MaintenanceOrders\Pages;

use App\Filament\Resources\MaintenanceOrders\Actions\CloseMaintenanceAction;
use App\Filament\Resources\MaintenanceOrders\MaintenanceOrderResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewMaintenanceOrder extends ViewRecord
{
    protected static string $resource = MaintenanceOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [CloseMaintenanceAction::make(), EditAction::make()];
    }
}
