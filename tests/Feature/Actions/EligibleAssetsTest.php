<?php

use App\Actions\Bookings\BookingData;
use App\Actions\Bookings\CreateBooking;
use App\Exceptions\InvalidBooking;
use App\Models\Asset;
use App\Models\Service;
use App\Services\Availability;

beforeEach(function () {
    $this->private = Service::factory()->private()->create();
    $this->parasail = Asset::factory()->capacity(4)->create(['name' => 'Parasail 1']);
    $this->cosita = Asset::factory()->capacity(12)->create(['name' => 'La Cosita']);
    $this->private->assets()->attach($this->cosita);
});

it('el privado solo usa sus unidades habilitadas aunque haya una más chica libre', function () {
    $booking = app(CreateBooking::class)->handle(new BookingData(
        customer: ['name' => 'Familia Ortiz'], adults: 2, serviceId: $this->private->id, startsAt: tomorrowAt('10:00'),
    ));

    expect($booking->departure->asset_id)->toBe($this->cosita->id);
});

it('la disponibilidad solo ofrece unidades habilitadas', function () {
    $slots = app(Availability::class)->for($this->private, tomorrowAt('10:00')->tz('America/Cancun')->toDateString(), 2);

    expect($slots->first()['assets']->pluck('name')->all())->toBe(['La Cosita']);
});

it('no programa una salida en una unidad no habilitada', function () {
    scheduleDeparture($this->private, $this->parasail);
})->throws(InvalidBooking::class, 'Parasail 1 no está habilitada para');

it('sin unidades configuradas acepta cualquiera de su tipo', function () {
    $open = Service::factory()->create();

    expect($open->allowsAsset($this->parasail))->toBeTrue()
        ->and($open->allowsAsset(Asset::factory()->vehicle()->create()))->toBeFalse();
});
