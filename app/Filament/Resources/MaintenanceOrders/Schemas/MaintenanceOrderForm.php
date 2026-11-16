<?php

namespace App\Filament\Resources\MaintenanceOrders\Schemas;

use App\Enums\MaintenanceType;
use App\Filament\Support\Domain;
use App\Models\Asset;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/** Al crear se usan todos los campos; al editar, horario y unidad quedan fijos (los cambia la agenda). */
class MaintenanceOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Orden')->columns(2)->columnSpanFull()->components([
                Select::make('asset_id')->label('Embarcación o vehículo')->required()->searchable()
                    ->options(fn () => Asset::query()->orderBy('kind')->orderBy('name')->get()
                        ->groupBy(fn (Asset $a) => $a->kind->getLabel())
                        ->map(fn ($group) => $group->pluck('name', 'id'))->all())
                    ->disabledOn('edit'),
                Select::make('type')->label('Tipo')->options(MaintenanceType::class)->required()->disabledOn('edit'),
                TextInput::make('title')->label('Trabajo')->required()->maxLength(150),
                DateTimePicker::make('starts_at')->label('Desde')->seconds(false)->required()->disabledOn('edit'),
                DateTimePicker::make('ends_at')->label('Hasta (estimado)')->seconds(false)->required()->after('starts_at')->disabledOn('edit'),
                TextInput::make('cost_cents')->label('Costo')->numeric()->prefix('$')->hiddenOn('create')
                    ->formatStateUsing(fn (?int $state) => Domain::toPesos($state))
                    ->dehydrateStateUsing(fn ($state) => Domain::toCents($state)),
                Textarea::make('description')->label('Detalle')->columnSpanFull(),
            ]),
        ]);
    }
}
