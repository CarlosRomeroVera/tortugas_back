<?php

namespace App\Filament\Resources\Boats\Pages;

use App\Filament\Resources\Boats\BoatResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBoat extends CreateRecord
{
    protected static string $resource = BoatResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [...$data, 'kind' => BoatResource::kind()];
    }
}
