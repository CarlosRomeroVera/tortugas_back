<?php

namespace App\Filament\Resources\Vehicles\Pages;

use App\Filament\Resources\Assets\Actions\BlockAssetAction;
use App\Filament\Resources\Assets\Actions\ScheduleMaintenanceAction;
use App\Filament\Resources\Vehicles\VehicleResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewVehicle extends ViewRecord
{
    protected static string $resource = VehicleResource::class;

    protected function getHeaderActions(): array
    {
        return [BlockAssetAction::make(), ScheduleMaintenanceAction::make(), EditAction::make()];
    }
}
