<?php

namespace App\Filament\Resources\MaintenanceOrders\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MaintenanceOrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(3)->columnSpanFull()->components([
                TextEntry::make('asset.name')->label('Unidad'),
                TextEntry::make('type')->label('Tipo')->badge(),
                TextEntry::make('status')->label('Estado')->badge(),
                TextEntry::make('title')->label('Trabajo'),
                TextEntry::make('starts_at')->label('Desde')->dateTime('D d M Y · H:i'),
                TextEntry::make('ends_at')->label('Hasta')->dateTime('D d M Y · H:i'),
                TextEntry::make('reading_at_service')->label('Lectura al cerrar')->numeric()->placeholder('—'),
                TextEntry::make('cost_cents')->label('Costo')->money('MXN', divideBy: 100)->placeholder('—'),
                TextEntry::make('description')->label('Detalle')->placeholder('—')->columnSpanFull(),
            ]),
        ]);
    }
}
