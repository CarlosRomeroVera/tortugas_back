<?php

namespace App\Filament\Resources\MaintenanceOrders\Tables;

use App\Enums\MaintenanceStatus;
use App\Enums\MaintenanceType;
use App\Filament\Resources\MaintenanceOrders\Actions\CloseMaintenanceAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MaintenanceOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('asset'))
            ->defaultSort('starts_at', 'desc')
            ->columns([
                TextColumn::make('code')->label('Orden')->searchable()->weight('bold'),
                TextColumn::make('asset.name')->label('Unidad')->searchable(),
                TextColumn::make('type')->label('Tipo')->badge(),
                TextColumn::make('title')->label('Trabajo')->searchable()->wrap(),
                TextColumn::make('starts_at')->label('Desde')->dateTime('D d M · H:i')->sortable(),
                TextColumn::make('ends_at')->label('Hasta')->dateTime('D d M · H:i'),
                TextColumn::make('status')->label('Estado')->badge(),
                TextColumn::make('cost_cents')->label('Costo')->money('MXN', divideBy: 100)->placeholder('—')->alignEnd(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Estado')->options(MaintenanceStatus::class)->multiple()
                    ->default([MaintenanceStatus::Scheduled->value, MaintenanceStatus::InProgress->value]),
                SelectFilter::make('type')->label('Tipo')->options(MaintenanceType::class),
                SelectFilter::make('asset')->label('Unidad')->relationship('asset', 'name'),
            ])
            ->recordActions([ViewAction::make(), EditAction::make(), CloseMaintenanceAction::make()]);
    }
}
