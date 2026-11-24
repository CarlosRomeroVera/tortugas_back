<?php

namespace App\Filament\Resources\Locations\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class LocationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextEntry::make('name')->label('Nombre'),
            TextEntry::make('type')->label('Tipo')->badge(),
            TextEntry::make('address')->label('Dirección')->placeholder('—'),
            TextEntry::make('pickup_notes')->label('Punto de encuentro')->placeholder('—'),
            TextEntry::make('latitude')->label('Latitud')->placeholder('—'),
            TextEntry::make('longitude')->label('Longitud')->placeholder('—'),
        ]);
    }
}
