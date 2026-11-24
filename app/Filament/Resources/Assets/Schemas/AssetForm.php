<?php

namespace App\Filament\Resources\Assets\Schemas;

use App\Enums\AssetKind;
use App\Enums\AssetStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class AssetForm
{
    public static function configure(Schema $schema, AssetKind $kind): Schema
    {
        $isBoat = $kind === AssetKind::Boat;
        $unit = $isBoat ? 'h' : 'km';

        return $schema->columns(2)->components([
            Section::make('Datos generales')->columns(2)->components([
                TextInput::make('name')->label('Nombre')->required()->maxLength(100)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('kind', $kind->value)),
                TextInput::make('registration')->label($isBoat ? 'Matrícula' : 'Placas')->maxLength(30),
                TextInput::make('model')->label($isBoat ? 'Motor / modelo' : 'Modelo')->maxLength(100),
                TextInput::make('capacity')->label('Capacidad (personas)')->required()->integer()->minValue(1)->maxValue(500),
                Select::make('status')->label('Estado')->options(AssetStatus::class)->default(AssetStatus::Active)->required()
                    ->helperText('Fuera de servicio impide venderla. Para un taller con fecha, usa una orden de mantenimiento.'),
            ]),
            Section::make($isBoat ? 'Horas de motor' : 'Kilometraje')->columns(2)->components([
                TextInput::make($isBoat ? 'engine_hours' : 'odometer_km')
                    ->label($isBoat ? 'Horas de motor actuales' : 'Kilometraje actual')->numeric()->minValue(0)->suffix($unit),
                TextInput::make('service_interval')->label('Servicio preventivo cada')->integer()->minValue(1)->suffix($unit),
                TextInput::make('last_service_reading')->label('Lectura en el último servicio')->numeric()->minValue(0)->suffix($unit),
            ]),
            Textarea::make('notes')->label('Notas')->columnSpanFull(),
        ]);
    }
}
