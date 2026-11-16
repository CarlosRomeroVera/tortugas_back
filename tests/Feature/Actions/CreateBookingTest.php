<?php

use App\Actions\Bookings\BookingData;
use App\Actions\Bookings\CancelBooking;
use App\Actions\Bookings\CreateBooking;
use App\Actions\Maintenance\ScheduleMaintenance;
use App\Enums\BookingStatus;
use App\Enums\DepartureStatus;
use App\Enums\MaintenanceType;
use App\Exceptions\AssetUnavailable;
use App\Exceptions\InvalidBooking;
use App\Exceptions\NoSeatsLeft;
use App\Models\Asset;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Service;

function book(array $overrides = []): Booking
{
    return app(CreateBooking::class)->handle(new BookingData(...array_merge([
        'customer' => ['name' => 'Emily Carter', 'email' => 'emily@example.com'],
        'adults' => 2,
    ], $overrides)));
}

describe('salida compartida', function () {
    beforeEach(function () {
        $this->departure = scheduleDeparture(asset: Asset::factory()->capacity(10)->create());
    });

    it('calcula el total con precio de adulto y niño', function () {
        $booking = book(['departureId' => $this->departure->id, 'adults' => 2, 'children' => 1]);

        expect($booking)
            ->status->toBe(BookingStatus::Pending)
            ->pax->toBe(3)
            ->total_cents->toBe(2 * 100000 + 50000);
    });

    it('no vende más lugares que la capacidad', function () {
        book(['departureId' => $this->departure->id, 'adults' => 8]);

        book(['departureId' => $this->departure->id, 'adults' => 3]);
    })->throws(NoSeatsLeft::class, 'quedan 2');

    it('libera los lugares al cancelar', function () {
        $booking = book(['departureId' => $this->departure->id, 'adults' => 10]);
        app(CancelBooking::class)->handle($booking, 'cliente canceló');

        book(['departureId' => $this->departure->id, 'adults' => 10]);

        expect($this->departure->fresh()->seatsLeft())->toBe(0)
            ->and($this->departure->fresh()->status)->toBe(DepartureStatus::Scheduled);
    });

    it('devuelve la misma reserva si se repite la clave de idempotencia', function () {
        $first = book(['departureId' => $this->departure->id, 'idempotencyKey' => 'abc-123']);
        $second = book(['departureId' => $this->departure->id, 'idempotencyKey' => 'abc-123']);

        expect($second->id)->toBe($first->id)
            ->and(Booking::count())->toBe(1);
    });

    it('reutiliza al cliente por correo', function () {
        book(['departureId' => $this->departure->id]);
        book(['departureId' => $this->departure->id]);

        expect(Customer::count())->toBe(1);
    });

    it('no admite reservas en una salida que ya zarpó', function () {
        $this->departure->update(['status' => DepartureStatus::InProgress]);

        book(['departureId' => $this->departure->id]);
    })->throws(InvalidBooking::class, 'ya no admite reservas');
});

describe('servicio privado', function () {
    beforeEach(function () {
        $this->service = Service::factory()->private()->create(['duration_minutes' => 240, 'buffer_minutes' => 30]);
        $this->small = Asset::factory()->capacity(8)->create(['name' => 'Laúd']);
        $this->big = Asset::factory()->capacity(12)->create(['name' => 'El Cielo']);
    });

    it('elige la embarcación más chica que alcance', function () {
        $booking = book(['serviceId' => $this->service->id, 'startsAt' => tomorrowAt('09:00'), 'adults' => 6]);

        expect($booking->departure->asset_id)->toBe($this->small->id)
            ->and($booking->departure->capacity)->toBe(8);
    });

    it('pasa a la siguiente embarcación si la primera está ocupada', function () {
        book(['serviceId' => $this->service->id, 'startsAt' => tomorrowAt('09:00'), 'adults' => 6]);
        $second = book(['serviceId' => $this->service->id, 'startsAt' => tomorrowAt('11:00'), 'adults' => 6]);

        expect($second->departure->asset_id)->toBe($this->big->id);
    });

    it('respeta el margen entre salidas', function () {
        // 09:00 + 240 min + 30 de margen = ocupada hasta 13:30
        book(['serviceId' => $this->service->id, 'startsAt' => tomorrowAt('09:00'), 'assetId' => $this->small->id, 'adults' => 2]);

        book(['serviceId' => $this->service->id, 'startsAt' => tomorrowAt('13:15'), 'assetId' => $this->small->id, 'adults' => 2]);
    })->throws(AssetUnavailable::class, 'Laúd ya está ocupada');

    it('falla si ninguna unidad está libre', function () {
        app(ScheduleMaintenance::class)->handle($this->small, MaintenanceType::Corrective, 'Motor', tomorrowAt('08:00'), tomorrowAt('18:00'));
        app(ScheduleMaintenance::class)->handle($this->big, MaintenanceType::Preventive, 'Servicio', tomorrowAt('08:00'), tomorrowAt('18:00'));

        book(['serviceId' => $this->service->id, 'startsAt' => tomorrowAt('10:00'), 'adults' => 2]);
    })->throws(AssetUnavailable::class, 'No hay ninguna unidad libre');

    it('libera la embarcación al cancelar', function () {
        $booking = book(['serviceId' => $this->service->id, 'startsAt' => tomorrowAt('09:00'), 'assetId' => $this->small->id, 'adults' => 2]);
        app(CancelBooking::class)->handle($booking);

        expect($booking->departure->fresh()->status)->toBe(DepartureStatus::Cancelled);

        $again = book(['serviceId' => $this->service->id, 'startsAt' => tomorrowAt('09:00'), 'assetId' => $this->small->id, 'adults' => 2]);
        expect($again->departure->asset_id)->toBe($this->small->id);
    });

    it('no permite reservar un servicio compartido sin elegir salida', function () {
        $shared = Service::factory()->create();

        book(['serviceId' => $shared->id, 'startsAt' => tomorrowAt('09:00')]);
    })->throws(InvalidBooking::class, 'salida compartida');
});
