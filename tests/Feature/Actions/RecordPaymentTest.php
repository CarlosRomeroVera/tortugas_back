<?php

use App\Actions\Bookings\BookingData;
use App\Actions\Bookings\CancelBooking;
use App\Actions\Bookings\CreateBooking;
use App\Actions\Payments\RecordPayment;
use App\Enums\BookingStatus;
use App\Enums\Currency;
use App\Enums\PaymentMethod;
use App\Exceptions\InvalidBooking;

beforeEach(function () {
    $departure = scheduleDeparture();
    $this->booking = app(CreateBooking::class)->handle(new BookingData(
        customer: ['name' => 'Daniel Ruiz'], adults: 2, departureId: $departure->id,
    )); // total 2,000.00 MXN
    $this->pay = app(RecordPayment::class);
});

it('confirma la reserva con el primer pago', function () {
    $this->pay->handle($this->booking, 100000, Currency::MXN, PaymentMethod::Cash);

    expect($this->booking->fresh())
        ->status->toBe(BookingStatus::Confirmed)
        ->balanceCents()->toBe(100000);
});

it('convierte un pago en dólares con el tipo de cambio del momento', function () {
    $payment = $this->pay->handle($this->booking, 5000, Currency::USD, PaymentMethod::Card, usdRate: 18.5);

    expect($payment->amount_mxn_cents)->toBe(92500)
        ->and($this->booking->fresh()->balanceCents())->toBe(200000 - 92500);
});

it('no cobra una reserva cancelada', function () {
    app(CancelBooking::class)->handle($this->booking);

    $this->pay->handle($this->booking->fresh(), 1000, Currency::MXN, PaymentMethod::Cash);
})->throws(InvalidBooking::class);
