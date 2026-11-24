<?php

namespace App\Filament\Resources\Customers\Tables;

use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('bookings'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->label('Nombre')->searchable()->weight('bold'),
                TextColumn::make('email')->label('Correo')->searchable()->placeholder('—'),
                TextColumn::make('phone')->label('Teléfono')->searchable()->placeholder('—'),
                TextColumn::make('country')->label('País')->placeholder('—'),
                TextColumn::make('bookings_count')->label('Reservas')->sortable(),
            ])
            ->recordActions([ViewAction::make(), EditAction::make()]);
    }
}
