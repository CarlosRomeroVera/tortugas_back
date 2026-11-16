<?php

namespace App\Filament\Resources\Staff\Tables;

use App\Enums\StaffRole;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class StaffTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Nombre')->searchable()->weight('bold'),
                TextColumn::make('role')->label('Puesto')->badge(),
                TextColumn::make('phone')->label('Teléfono')->placeholder('—'),
                TextColumn::make('license_expires_on')->label('Licencia vence')->date('d M Y')->sortable()->placeholder('—')
                    ->color(fn ($state) => $state && $state->isBefore(now()->addDays(30)) ? 'danger' : null),
                IconColumn::make('user_id')->label('App')->boolean()->state(fn ($record) => filled($record->user_id)),
                IconColumn::make('is_active')->label('Activo')->boolean(),
            ])
            ->filters([
                SelectFilter::make('role')->label('Puesto')->options(StaffRole::class),
                TernaryFilter::make('is_active')->label('Activo')->default(true),
            ])
            ->recordActions([ViewAction::make(), EditAction::make()]);
    }
}
