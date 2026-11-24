<?php

namespace App\Filament\Resources\Assets\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MaintenanceOrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'maintenanceOrders';

    protected static ?string $title = 'Bitácora de mantenimiento';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_at', 'desc')
            ->columns([
                TextColumn::make('code')->label('Orden'),
                TextColumn::make('type')->label('Tipo')->badge(),
                TextColumn::make('title')->label('Trabajo'),
                TextColumn::make('starts_at')->label('Desde')->dateTime('d M Y H:i'),
                TextColumn::make('status')->label('Estado')->badge(),
                TextColumn::make('cost_cents')->label('Costo')->money('MXN', divideBy: 100)->placeholder('—'),
            ]);
    }
}
