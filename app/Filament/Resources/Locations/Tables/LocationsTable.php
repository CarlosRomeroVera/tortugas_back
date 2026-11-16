<?php

namespace App\Filament\Resources\Locations\Tables;

use App\Enums\LocationType;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LocationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Nombre')->searchable()->weight('bold'),
                TextColumn::make('type')->label('Tipo')->badge(),
                TextColumn::make('pickup_notes')->label('Punto de encuentro')->placeholder('—'),
                TextColumn::make('address')->label('Dirección')->placeholder('—')->toggleable(),
            ])
            ->filters([SelectFilter::make('type')->label('Tipo')->options(LocationType::class)])
            ->recordActions([EditAction::make()]);
    }
}
