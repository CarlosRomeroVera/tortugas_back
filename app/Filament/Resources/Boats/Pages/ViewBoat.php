<?php

namespace App\Filament\Resources\Boats\Pages;

use App\Filament\Resources\Assets\Actions\BlockAssetAction;
use App\Filament\Resources\Assets\Actions\ScheduleMaintenanceAction;
use App\Filament\Resources\Boats\BoatResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewBoat extends ViewRecord
{
    protected static string $resource = BoatResource::class;

    protected function getHeaderActions(): array
    {
        return [BlockAssetAction::make(), ScheduleMaintenanceAction::make(), EditAction::make()];
    }
}
