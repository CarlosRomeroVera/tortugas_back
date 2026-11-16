<?php

namespace App\Filament\Resources\Staff\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class StaffInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            TextEntry::make('name')->label('Nombre'),
            TextEntry::make('role')->label('Puesto')->badge(),
            IconEntry::make('is_active')->label('Activo')->boolean(),
            TextEntry::make('phone')->label('Teléfono')->placeholder('—'),
            TextEntry::make('user.email')->label('Usuario de la app')->placeholder('Sin acceso'),
            TextEntry::make('license_number')->label('Licencia')->placeholder('—'),
            TextEntry::make('license_expires_on')->label('Vence')->date('d M Y')->placeholder('—')
                ->color(fn ($state) => $state && $state->isBefore(now()->addDays(30)) ? 'danger' : null),
        ]);
    }
}
