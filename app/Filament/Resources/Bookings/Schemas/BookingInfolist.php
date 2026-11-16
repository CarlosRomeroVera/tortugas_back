<?php

namespace App\Filament\Resources\Bookings\Schemas;

use App\Filament\Resources\Departures\DepartureResource;
use App\Models\Booking;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BookingInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $money = fn (string $name, string $label, \Closure $state) => TextEntry::make($name)->label($label)
            ->state($state)->money(fn (Booking $r) => $r->currency->value, divideBy: 100);

        return $schema->columns(3)->components([
            Section::make('Reserva')->columnSpan(2)->columns(3)->components([
                TextEntry::make('status')->label('Estado')->badge(),
                TextEntry::make('channel')->label('Canal')->badge(),
                TextEntry::make('agency_reference')->label('Voucher')->placeholder('—'),
                TextEntry::make('departure.service.name')->label('Servicio'),
                TextEntry::make('departure.starts_at')->label('Sale')->dateTime('D d M Y · H:i')
                    ->url(fn (Booking $r) => DepartureResource::getUrl('view', ['record' => $r->departure_id])),
                TextEntry::make('departure.asset.name')->label('Unidad'),
                TextEntry::make('pax')->label('Personas')
                    ->formatStateUsing(fn (Booking $r) => "{$r->adults} adultos".($r->children ? " · {$r->children} niños" : '')),
                TextEntry::make('cruise_ship')->label('Crucero')->placeholder('—'),
                TextEntry::make('all_aboard_at')->label('All aboard')->dateTime('H:i')->placeholder('—'),
                TextEntry::make('pickupLocation.name')->label('Recoger en')->placeholder('Llega por su cuenta'),
            ]),
            Section::make('Cobro')->columnSpan(1)->components([
                $money('total', 'Total', fn (Booking $r) => $r->total_cents),
                $money('paid', 'Cobrado', fn (Booking $r) => $r->paidCents())->color('success'),
                $money('balance', 'Saldo', fn (Booking $r) => $r->balanceCents())
                    ->color(fn (Booking $r) => $r->balanceCents() > 0 ? 'warning' : 'success'),
            ]),
            Section::make('Cliente')->columnSpan(2)->columns(3)->components([
                TextEntry::make('customer.name')->label('Nombre'),
                TextEntry::make('customer.email')->label('Correo')->placeholder('—')->copyable(),
                TextEntry::make('customer.phone')->label('Teléfono')->placeholder('—')->copyable(),
            ]),
            TextEntry::make('notes')->label('Notas')->placeholder('—')->columnSpanFull(),
        ]);
    }
}
