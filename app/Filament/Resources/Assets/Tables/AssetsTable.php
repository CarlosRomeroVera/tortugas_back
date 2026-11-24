<?php

namespace App\Filament\Resources\Assets\Tables;

use App\Enums\AssetKind;
use App\Enums\AssetStatus;
use App\Filament\Resources\Assets\Actions\BlockAssetAction;
use App\Filament\Resources\Assets\Actions\ScheduleMaintenanceAction;
use App\Filament\Resources\Assets\Schemas\AssetsColors;
use App\Models\Asset;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AssetsTable
{
    public static function configure(Table $table, AssetKind $kind): Table
    {
        $isBoat = $kind === AssetKind::Boat;
        $unit = $isBoat ? 'h' : 'km';

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['upcomingAllocations' => fn ($a) => $a->where('starts_at', '<', now()->addDays(2))]))
            ->defaultSort('name')
            ->poll('30s')
            ->columns([
                TextColumn::make('name')->label($isBoat ? 'Embarcación' : 'Vehículo')->weight('bold')->searchable()->sortable()
                    ->description(fn (Asset $r) => collect([$r->registration, $r->model])->filter()->join(' · ')),
                TextColumn::make('capacity')->label('Capacidad')->numeric()->sortable()->alignEnd(),
                TextColumn::make($isBoat ? 'engine_hours' : 'odometer_km')->label($isBoat ? 'Horas de motor' : 'Kilometraje')
                    ->numeric(decimalPlaces: 0)->suffix(" {$unit}")->sortable()
                    ->description(fn (Asset $r) => $r->nextServiceReading() ? 'servicio a las '.number_format($r->nextServiceReading()).' '.$unit : null)
                    ->color(fn (Asset $r) => AssetsColors::forService($r)),
                TextColumn::make('now')->label('Estado ahora')->badge()
                    ->state(fn (Asset $r) => $r->snapshot()['label'])
                    ->color(fn (Asset $r) => AssetsColors::forSnapshot($r->snapshot())),
                TextColumn::make('free_at')->label('Libre a las')
                    ->state(fn (Asset $r) => AssetsColors::freeAtText($r->snapshot())),
                TextColumn::make('status')->label('Estado administrativo')->badge()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->label('Estado administrativo')->options(AssetStatus::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make([
                    EditAction::make(),
                    BlockAssetAction::make(),
                    ScheduleMaintenanceAction::make(),
                ]),
            ]);
    }
}
