<?php

namespace App\Filament\Resources\Departures\RelationManagers;

use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BookingsRelationManager extends RelationManager
{
    protected static string $relationship = 'bookings';

    protected static ?string $title = 'Pasajeros';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['customer', 'payments', 'pickupLocation']))
            ->columns([
                TextColumn::make('code')->label('Reserva')->searchable(),
                TextColumn::make('customer.name')->label('Cliente')->searchable()
                    ->description(fn (Booking $r) => $r->cruise_ship ? "Crucero {$r->cruise_ship}" : null),
                TextColumn::make('pax')->label('Pax')->alignEnd(),
                TextColumn::make('channel')->label('Canal')->badge(),
                TextColumn::make('pickupLocation.name')->label('Recogida')->placeholder('Llega por su cuenta'),
                TextColumn::make('status')->label('Estado')->badge(),
                TextColumn::make('checked_in_at')->label('Abordó')->dateTime('H:i')->placeholder('—'),
                TextColumn::make('balance')->label('Saldo')
                    ->state(fn (Booking $r) => $r->balanceCents())
                    ->money(fn (Booking $r) => $r->currency->value, divideBy: 100)
                    ->color(fn (Booking $r) => $r->balanceCents() > 0 ? 'warning' : 'success'),
            ])
            ->recordActions([
                Action::make('open')->label('Ver')->url(fn (Booking $r) => BookingResource::getUrl('view', ['record' => $r])),
            ]);
    }
}
