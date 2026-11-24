<?php

namespace App\Filament\Resources\Assets\Schemas;

use App\Enums\AssetKind;
use App\Models\Asset;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AssetInfolist
{
    public static function configure(Schema $schema, AssetKind $kind): Schema
    {
        $isBoat = $kind === AssetKind::Boat;
        $unit = $isBoat ? 'h' : 'km';

        return $schema->columns(3)->components([
            Section::make('Ahora')->columnSpan(1)->components([
                TextEntry::make('now')->label('Estado')->badge()
                    ->state(fn (Asset $record) => $record->snapshot()['label'])
                    ->color(fn (Asset $record) => AssetsColors::forSnapshot($record->snapshot())),
                TextEntry::make('free_at')->label('Libre a las')
                    ->state(fn (Asset $record) => AssetsColors::freeAtText($record->snapshot())),
            ]),
            Section::make('Datos')->columnSpan(1)->components([
                TextEntry::make('registration')->label($isBoat ? 'Matrícula' : 'Placas')->placeholder('—'),
                TextEntry::make('model')->label('Modelo')->placeholder('—'),
                TextEntry::make('capacity')->label('Capacidad')->suffix(' personas'),
                TextEntry::make('status')->label('Estado administrativo')->badge(),
            ]),
            Section::make($isBoat ? 'Horas de motor' : 'Kilometraje')->columnSpan(1)->components([
                TextEntry::make('reading')->label('Lectura actual')
                    ->state(fn (Asset $record) => $record->currentReading())->numeric()->suffix(" {$unit}")->placeholder('—'),
                TextEntry::make('next_service')->label('Siguiente servicio')
                    ->state(fn (Asset $record) => $record->nextServiceReading())->numeric()->suffix(" {$unit}")->placeholder('Sin intervalo definido')
                    ->color(fn (Asset $record) => AssetsColors::forService($record)),
            ]),
            TextEntry::make('notes')->label('Notas')->placeholder('—')->columnSpanFull(),
        ]);
    }
}
