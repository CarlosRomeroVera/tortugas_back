<?php

namespace App\Filament\Resources\Customers\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class CustomerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextEntry::make('name')->label('Nombre'),
            TextEntry::make('email')->label('Correo')->placeholder('—')->copyable(),
            TextEntry::make('phone')->label('Teléfono')->placeholder('—')->copyable(),
            TextEntry::make('country')->label('País')->placeholder('—'),
            TextEntry::make('bookings_count')->label('Reservas')->state(fn ($record) => $record->bookings()->count()),
            TextEntry::make('notes')->label('Notas')->placeholder('—')->columnSpanFull(),
        ]);
    }
}
