<?php

namespace App\Filament\Resources\Departures\Schemas;

use App\Models\Departure;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DepartureInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('Salida')->columnSpan(2)->columns(3)->components([
                TextEntry::make('service.name')->label('Servicio'),
                TextEntry::make('asset.name')->label('Unidad'),
                TextEntry::make('status')->label('Estado')->badge(),
                TextEntry::make('starts_at')->label('Sale')->dateTime('D d M Y · H:i'),
                TextEntry::make('ends_at')->label('Regresa')->dateTime('H:i'),
                TextEntry::make('currentStage.name')->label('Etapa actual')->placeholder('Sin iniciar'),
                TextEntry::make('departed_at')->label('Inició')->dateTime('H:i')->placeholder('—'),
                TextEntry::make('returned_at')->label('Terminó')->dateTime('H:i')->placeholder('—'),
                TextEntry::make('captain')->label('Tripulación')
                    ->state(fn (Departure $r) => $r->crew->pluck('name')->join(', ') ?: null)
                    ->placeholder('Sin asignar')
                    ->color(fn (Departure $r) => $r->crew->isEmpty() ? 'danger' : null),
            ]),
            Section::make('Ocupación')->columnSpan(1)->components([
                TextEntry::make('seats')->label('Lugares')
                    ->state(fn (Departure $r) => $r->seatsTaken().' de '.$r->capacity)
                    ->color(fn (Departure $r) => $r->seatsLeft() === 0 ? 'danger' : null),
                TextEntry::make('sold')->label('Vendido')
                    ->state(fn (Departure $r) => $r->bookings()->occupyingSeats()->sum('total_cents'))
                    ->money(fn (Departure $r) => $r->service->currency->value, divideBy: 100),
            ]),
            TextEntry::make('notes')->label('Notas')->placeholder('—')->columnSpanFull(),
        ]);
    }
}
