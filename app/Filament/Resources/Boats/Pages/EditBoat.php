<?php

namespace App\Filament\Resources\Boats\Pages;

use App\Filament\Resources\Boats\BoatResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditBoat extends EditRecord
{
    protected static string $resource = BoatResource::class;

    protected function getHeaderActions(): array
    {
        return [ViewAction::make(), DeleteAction::make()];
    }
}
