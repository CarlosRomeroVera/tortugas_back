<?php

namespace App\Filament\Resources\Locations\Schemas;

use App\Enums\LocationType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class LocationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label('Nombre')->required()->maxLength(150),
            Select::make('type')->label('Tipo')->options(LocationType::class)->required(),
            TextInput::make('address')->label('Dirección')->maxLength(255)->columnSpanFull(),
            TextInput::make('latitude')->label('Latitud')->numeric()->minValue(-90)->maxValue(90),
            TextInput::make('longitude')->label('Longitud')->numeric()->minValue(-180)->maxValue(180),
            TextInput::make('pickup_notes')->label('Punto de encuentro')->placeholder('Lobby principal, salida peatonal…')->columnSpanFull(),
        ]);
    }
}
