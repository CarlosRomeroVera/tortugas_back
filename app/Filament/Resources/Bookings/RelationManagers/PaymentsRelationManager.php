<?php

namespace App\Filament\Resources\Bookings\RelationManagers;

use App\Models\Payment;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Pagos';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('paid_at')->label('Fecha')->dateTime('d M Y H:i')->placeholder('—'),
                TextColumn::make('amount_cents')->label('Monto')->money(fn (Payment $r) => $r->currency->value, divideBy: 100),
                TextColumn::make('amount_mxn_cents')->label('En pesos')->money('MXN', divideBy: 100),
                TextColumn::make('exchange_rate')->label('Tipo de cambio')->numeric(decimalPlaces: 2),
                TextColumn::make('method')->label('Forma')->badge(),
                TextColumn::make('status')->label('Estado')->badge(),
                TextColumn::make('provider_reference')->label('Referencia')->placeholder('—'),
            ]);
    }
}
