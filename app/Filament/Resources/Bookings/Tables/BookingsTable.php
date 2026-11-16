<?php

namespace App\Filament\Resources\Bookings\Tables;

use App\Enums\BookingStatus;
use App\Enums\SalesChannel;
use App\Models\Booking;
use App\Support\LocalTime;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BookingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['customer', 'departure.service', 'departure.asset', 'payments']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('code')->label('Reserva')->searchable()->weight('bold'),
                TextColumn::make('customer.name')->label('Cliente')->searchable()
                    ->description(fn (Booking $r) => $r->cruise_ship ? "Crucero {$r->cruise_ship}" : $r->customer->email),
                TextColumn::make('departure.service.name')->label('Servicio')
                    ->description(fn (Booking $r) => $r->departure->asset->name),
                TextColumn::make('departure.starts_at')->label('Sale')->dateTime('D d M · H:i')->sortable(),
                TextColumn::make('pax')->label('Pax')->alignEnd(),
                TextColumn::make('channel')->label('Canal')->badge(),
                TextColumn::make('status')->label('Estado')->badge(),
                TextColumn::make('total_cents')->label('Total')->money(fn (Booking $r) => $r->currency->value, divideBy: 100)->sortable(),
                TextColumn::make('balance')->label('Saldo')
                    ->state(fn (Booking $r) => $r->balanceCents())
                    ->money(fn (Booking $r) => $r->currency->value, divideBy: 100)
                    ->color(fn (Booking $r) => $r->balanceCents() > 0 ? 'warning' : 'success'),
                TextColumn::make('created_at')->label('Vendida')->since()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->label('Estado')->options(BookingStatus::class)->multiple(),
                SelectFilter::make('channel')->label('Canal')->options(SalesChannel::class)->multiple(),
                Filter::make('date')
                    ->schema([DatePicker::make('date')->label('Fecha de salida')])
                    ->query(function (Builder $query, array $data) {
                        if ($data['date'] ?? null) {
                            [$from, $to] = LocalTime::dayBounds($data['date']);
                            $query->whereHas('departure', fn ($d) => $d->where('starts_at', '>=', $from)->where('starts_at', '<', $to));
                        }
                    }),
            ])
            ->recordActions([ViewAction::make()]);
    }
}
