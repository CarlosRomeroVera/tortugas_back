<?php

namespace App\Filament\Resources\Boats\Pages;

use App\Filament\Resources\Boats\BoatResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBoats extends ListRecords
{
    protected static string $resource = BoatResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
