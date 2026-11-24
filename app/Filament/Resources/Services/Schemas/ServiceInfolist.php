<?php

namespace App\Filament\Resources\Services\Schemas;

use App\Models\Service;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ServiceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('Servicio')->columnSpan(2)->columns(3)->components([
                TextEntry::make('category')->label('Categoría')->badge(),
                TextEntry::make('asset_kind')->label('Usa')->badge(),
                TextEntry::make('is_shared')->label('Tipo')->badge()
                    ->formatStateUsing(fn (bool $state) => $state ? 'Compartida' : 'Privada'),
                TextEntry::make('duration_minutes')->label('Duración')->suffix(' min'),
                TextEntry::make('buffer_minutes')->label('Margen')->suffix(' min'),
                TextEntry::make('default_capacity')->label('Cupo')->placeholder('Capacidad de la unidad'),
                TextEntry::make('price_adult_cents')->label('Adulto')->money(fn (Service $r) => $r->currency->value, divideBy: 100),
                TextEntry::make('price_child_cents')->label('Niño')->money(fn (Service $r) => $r->currency->value, divideBy: 100)->placeholder('Igual que adulto'),
                TextEntry::make('slug')->label('Identificador')->copyable()->fontFamily('mono'),
            ]),
            Section::make('Etapas')->columnSpan(1)->components([
                RepeatableEntry::make('stages')->hiddenLabel()->components([
                    TextEntry::make('name')->hiddenLabel()->prefix(fn ($record) => $record->position.'. '),
                ])->placeholder('Sin etapas'),
            ]),
            TextEntry::make('description')->label('Descripción')->placeholder('—')->columnSpanFull(),
        ]);
    }
}
