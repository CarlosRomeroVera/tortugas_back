<?php

namespace App\Filament\Resources\Departures\Pages;

use App\Actions\Departures\CancelDeparture;
use App\Enums\DepartureStatus;
use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\Departures\DepartureResource;
use App\Filament\Support\Domain;
use App\Models\Departure;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewDeparture extends ViewRecord
{
    protected static string $resource = DepartureResource::class;

    public function getTitle(): string
    {
        /** @var Departure $d */
        $d = $this->record;

        return "{$d->service->name} · {$d->starts_at->tz(config('tortugas.display_timezone'))->format('H:i')} · {$d->asset->name}";
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('book')->label('Nueva reserva')->icon(Heroicon::OutlinedPlus)
                ->url(fn () => BookingResource::getUrl('create', ['departure' => $this->record->getKey()]))
                ->visible(fn () => $this->record->status === DepartureStatus::Scheduled && $this->record->seatsLeft() > 0),
            Action::make('cancel')->label('Cancelar salida')->color('danger')->icon(Heroicon::OutlinedXCircle)
                ->requiresConfirmation()
                ->modalDescription('Se cancelan también sus reservas activas y la unidad queda libre.')
                ->schema([TextInput::make('reason')->label('Motivo')->required()->placeholder('Cierre de puerto, clima, falla mecánica…')])
                ->visible(fn () => $this->record->status === DepartureStatus::Scheduled)
                ->action(fn (array $data) => Domain::run(
                    fn () => app(CancelDeparture::class)->handle($this->record, $data['reason']),
                    success: 'Salida cancelada',
                )),
        ];
    }
}
