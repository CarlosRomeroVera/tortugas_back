<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Actions\Bookings\CancelBooking;
use App\Actions\Payments\RecordPayment;
use App\Enums\BookingStatus;
use App\Enums\Currency;
use App\Enums\PaymentMethod;
use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Support\Domain;
use App\Models\Booking;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;

class ViewBooking extends ViewRecord
{
    protected static string $resource = BookingResource::class;

    public function getTitle(): string
    {
        return "Reserva {$this->record->code}";
    }

    protected function getHeaderActions(): array
    {
        /** @var Booking $booking */
        $booking = $this->record;

        return [
            Action::make('pay')->label('Registrar pago')->icon(Heroicon::OutlinedBanknotes)->color('success')
                ->visible(fn () => $booking->status !== BookingStatus::Cancelled && $booking->balanceCents() > 0)
                ->modalWidth('md')
                ->fillForm(fn () => [
                    'amount' => number_format($booking->balanceCents() / 100, 2, '.', ''),
                    'currency' => $booking->currency,
                    'method' => PaymentMethod::Cash,
                    'usd_rate' => config('tortugas.default_usd_rate'),
                ])
                ->schema([
                    TextInput::make('amount')->label('Monto')->numeric()->minValue(0.01)->required()->prefix('$'),
                    Select::make('currency')->label('Moneda')->options(Currency::class)->required()->live(),
                    Select::make('method')->label('Forma de pago')->options(PaymentMethod::class)->required(),
                    TextInput::make('usd_rate')->label('Tipo de cambio (pesos por dólar)')->numeric()->minValue(1)
                        ->visible(fn (Get $get) => $this->isUsd($get('currency')) || $booking->currency === Currency::USD),
                ])
                ->action(fn (array $data) => Domain::run(fn () => app(RecordPayment::class)->handle(
                    $booking,
                    Domain::toCents($data['amount']),
                    $this->isUsd($data['currency']) ? Currency::USD : Currency::MXN,
                    $data['method'] instanceof PaymentMethod ? $data['method'] : PaymentMethod::from($data['method']),
                    filled($data['usd_rate'] ?? null) ? (float) $data['usd_rate'] : null,
                ), success: 'Pago registrado')),
            Action::make('cancel')->label('Cancelar')->color('danger')->icon(Heroicon::OutlinedXCircle)
                ->visible(fn () => in_array($booking->status, BookingStatus::occupyingSeats(), true))
                ->requiresConfirmation()
                ->modalDescription('Libera los lugares. Si es un servicio privado, también libera la embarcación. Los reembolsos se registran aparte.')
                ->schema([TextInput::make('reason')->label('Motivo')->required()])
                ->action(fn (array $data) => Domain::run(
                    fn () => app(CancelBooking::class)->handle($booking, $data['reason']),
                    success: 'Reserva cancelada',
                )),
            EditAction::make()->label('Editar detalles'),
        ];
    }

    private function isUsd(mixed $currency): bool
    {
        return $currency === Currency::USD || $currency === Currency::USD->value;
    }
}
