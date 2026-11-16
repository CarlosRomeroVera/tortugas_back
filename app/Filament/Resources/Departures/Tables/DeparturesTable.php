<?php

namespace App\Filament\Resources\Departures\Tables;

use App\Enums\DepartureStatus;
use App\Models\Departure;
use App\Support\LocalTime;
use Carbon\Carbon;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DeparturesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['service', 'asset', 'currentStage'])
                ->withSum(['bookings as seats_taken' => fn ($b) => $b->occupyingSeats()], 'pax'))
            ->defaultSort('starts_at')
            ->poll('30s')
            ->columns([
                TextColumn::make('starts_at')->label('Sale')->dateTime('D d M · H:i')->sortable()
                    ->description(fn (Departure $r) => 'regresa '.$r->ends_at->tz(LocalTime::zone())->format('H:i')),
                TextColumn::make('service.name')->label('Servicio')->searchable(),
                TextColumn::make('asset.name')->label('Unidad')->searchable(),
                TextColumn::make('seats_taken')->label('Lugares')
                    ->formatStateUsing(fn ($state, Departure $r) => ((int) $state).' / '.$r->capacity)
                    ->default(0)
                    ->color(fn ($state, Departure $r) => (int) $state >= $r->capacity ? 'danger' : null),
                TextColumn::make('status')->label('Estado')->badge(),
                TextColumn::make('currentStage.name')->label('Etapa')->placeholder('—'),
                TextColumn::make('code')->label('Código')->searchable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('date')
                    ->schema([DatePicker::make('date')->label('Fecha')->default(LocalTime::today())])
                    ->query(function (Builder $query, array $data) {
                        if (! $data['date']) {
                            return;
                        }
                        [$from, $to] = LocalTime::dayBounds($data['date']);
                        $query->where('starts_at', '>=', $from)->where('starts_at', '<', $to);
                    })
                    ->indicateUsing(fn (array $data) => $data['date'] ? 'Fecha: '.Carbon::parse($data['date'])->translatedFormat('D d M') : null),
                SelectFilter::make('service')->label('Servicio')->relationship('service', 'name'),
                SelectFilter::make('status')->label('Estado')->options(DepartureStatus::class)->multiple(),
            ])
            ->recordActions([ViewAction::make()]);
    }
}
