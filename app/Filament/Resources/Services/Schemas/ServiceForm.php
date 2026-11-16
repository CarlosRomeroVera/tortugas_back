<?php

namespace App\Filament\Resources\Services\Schemas;

use App\Enums\AssetKind;
use App\Enums\Currency;
use App\Enums\ServiceCategory;
use App\Filament\Support\Domain;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ServiceForm
{
    public static function configure(Schema $schema): Schema
    {
        $weekdays = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

        return $schema->columns(2)->components([
            Section::make('Servicio')->columns(2)->columnSpanFull()->components([
                TextInput::make('name')->label('Nombre')->required()->maxLength(150)->live(onBlur: true)
                    ->afterStateUpdated(fn (Set $set, ?string $state, string $operation) => $operation === 'create' ? $set('slug', Str::slug($state)) : null),
                TextInput::make('slug')->label('Identificador (API y sitio web)')->required()->maxLength(150)->unique(ignoreRecord: true)->alphaDash(),
                Select::make('category')->label('Categoría')->options(ServiceCategory::class)->required(),
                Select::make('asset_kind')->label('Usa')->options(AssetKind::class)->required()->live(),
                Toggle::make('is_shared')->label('Salida compartida con horarios fijos')->default(true)->live()
                    ->helperText('Compartida: se vende por lugar. Privada: se reserva la unidad completa.'),
                Toggle::make('is_active')->label('Se vende')->default(true),
                TextInput::make('duration_minutes')->label('Duración')->required()->integer()->minValue(10)->suffix('min'),
                TextInput::make('buffer_minutes')->label('Margen entre salidas')->integer()->minValue(0)->default(0)->suffix('min')
                    ->helperText('Tiempo para limpiar, cargar combustible o regresar.'),
                TextInput::make('default_capacity')->label('Cupo por salida')->integer()->minValue(1)
                    ->helperText('Vacío = capacidad de la unidad.')->visible(fn (Get $get) => (bool) $get('is_shared')),
            ]),
            Section::make('Precio')->columns(3)->columnSpanFull()->components([
                Select::make('currency')->label('Moneda')->options(Currency::class)->default(Currency::MXN)->required(),
                self::money('price_adult_cents', 'Adulto')->required(),
                self::money('price_child_cents', 'Niño')->helperText('Vacío = precio de adulto.'),
            ]),
            Section::make('Etapas')->description('Lo que marca el capitán o guía en la app, en orden.')->columnSpan(1)->components([
                Repeater::make('stages')->hiddenLabel()->relationship()->orderColumn('position')->reorderable()
                    ->simple(TextInput::make('name')->required()->maxLength(100))
                    ->defaultItems(0)->addActionLabel('Agregar etapa'),
            ]),
            Section::make('Horarios fijos')->description('Para generar las salidas compartidas.')->columnSpan(1)
                ->visible(fn (Get $get) => (bool) $get('is_shared'))->components([
                    Repeater::make('schedules')->hiddenLabel()->relationship()->columns(3)->defaultItems(0)
                        ->addActionLabel('Agregar horario')
                        ->schema([
                            Select::make('weekday')->label('Día')->options($weekdays)->required(),
                            TimePicker::make('departs_at')->label('Hora')->seconds(false)->required(),
                            Select::make('asset_id')->label('Unidad')->required()
                                ->relationship('asset', 'name', fn ($query, Get $get) => $query->where('kind', $get('../../asset_kind'))),
                        ]),
                ]),
            Textarea::make('description')->label('Descripción')->columnSpanFull(),
        ]);
    }

    private static function money(string $field, string $label): TextInput
    {
        return TextInput::make($field)->label($label)->numeric()->minValue(0)->prefix('$')
            ->formatStateUsing(fn (?int $state) => Domain::toPesos($state))
            ->dehydrateStateUsing(fn ($state) => Domain::toCents($state));
    }
}
