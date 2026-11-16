<?php

namespace App\Filament\Resources\Services\Tables;

use App\Enums\ServiceCategory;
use App\Models\Service;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ServicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount(['stages', 'schedules']))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Servicio')->weight('bold')->searchable()->sortable(),
                TextColumn::make('category')->label('Categoría')->badge(),
                TextColumn::make('is_shared')->label('Tipo')->badge()->formatStateUsing(fn (bool $state) => $state ? 'Compartida' : 'Privada')
                    ->color(fn (bool $state) => $state ? 'info' : 'primary'),
                TextColumn::make('duration_minutes')->label('Duración')->suffix(' min')->sortable(),
                TextColumn::make('price_adult_cents')->label('Adulto')->money(fn (Service $r) => $r->currency->value, divideBy: 100)->sortable(),
                TextColumn::make('stages_count')->label('Etapas'),
                TextColumn::make('schedules_count')->label('Horarios'),
                IconColumn::make('is_active')->label('Se vende')->boolean(),
            ])
            ->filters([
                SelectFilter::make('category')->label('Categoría')->options(ServiceCategory::class),
                TernaryFilter::make('is_active')->label('Se vende'),
            ])
            ->recordActions([ViewAction::make(), EditAction::make()]);
    }
}
