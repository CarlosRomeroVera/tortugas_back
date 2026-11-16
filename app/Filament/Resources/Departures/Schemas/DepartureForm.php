<?php

namespace App\Filament\Resources\Departures\Schemas;

use App\Models\Service;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/** Solo para crear: cambiar hora o unidad de una salida existente se hará con "Reprogramar" (fase 2). */
class DepartureForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Nueva salida')->columns(2)->description('La embarcación o vehículo queda ocupado durante la duración del servicio más su margen.')
                ->components([
                    Select::make('service_id')->label('Servicio')->required()->live()
                        ->relationship('service', 'name', fn ($query) => $query->where('is_active', true)),
                    Select::make('asset_id')->label('Unidad')->required()
                        ->relationship('asset', 'name', fn ($query, Get $get) => $query
                            ->where('kind', Service::find($get('service_id'))?->asset_kind)
                            ->where('status', 'active'))
                        ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->name} · {$record->capacity} pax")
                        ->disabled(fn (Get $get) => ! $get('service_id')),
                    DateTimePicker::make('starts_at')->label('Sale')->seconds(false)->required()->minutesStep(5),
                    TextInput::make('capacity')->label('Cupo')->integer()->minValue(1)
                        ->helperText('Vacío = cupo del servicio o capacidad de la unidad.'),
                ]),
        ]);
    }
}
