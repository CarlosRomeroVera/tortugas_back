<?php

use App\Actions\Crew\AssignCrew;
use App\Actions\Departures\CancelDeparture;
use App\Enums\StaffRole;
use App\Exceptions\CrewUnavailable;
use App\Exceptions\InvalidBooking;
use App\Models\Asset;
use App\Models\Staff;

beforeEach(function () {
    $this->staff = Staff::create(['name' => 'Jorge May', 'role' => StaffRole::Captain]);
    $this->assign = app(AssignCrew::class);
});

it('asigna con la función de su ficha', function () {
    $departure = scheduleDeparture(at: '10:00');

    $this->assign->handle($departure, $this->staff);

    expect($departure->crew()->first()->pivot->role)->toBe('captain');
});

it('no deja a la misma persona en dos salidas que se traslapan', function () {
    $this->assign->handle(scheduleDeparture(at: '10:00'), $this->staff); // 10:00 a 12:00

    $this->assign->handle(scheduleDeparture(asset: Asset::factory()->create(), at: '11:00'), $this->staff);
})->throws(CrewUnavailable::class, 'Jorge May ya va en');

it('sí la deja en una salida que empieza cuando termina la otra', function () {
    $this->assign->handle(scheduleDeparture(at: '10:00'), $this->staff);
    $this->assign->handle($later = scheduleDeparture(asset: Asset::factory()->create(), at: '12:00'), $this->staff);

    expect($later->crew()->count())->toBe(1);
});

it('ignora salidas canceladas al revisar traslapes', function () {
    $first = scheduleDeparture(at: '10:00');
    $this->assign->handle($first, $this->staff);
    app(CancelDeparture::class)->handle($first, 'Clima');

    $this->assign->handle($second = scheduleDeparture(asset: Asset::factory()->create(), at: '10:00'), $this->staff);

    expect($second->crew()->count())->toBe(1);
});

it('no asigna personal dado de baja ni en salidas canceladas', function () {
    $this->staff->update(['is_active' => false]);

    expect(fn () => $this->assign->handle(scheduleDeparture(), $this->staff))->toThrow(CrewUnavailable::class);

    $cancelled = app(CancelDeparture::class)->handle(scheduleDeparture(asset: Asset::factory()->create(), at: '15:00'));
    expect(fn () => $this->assign->handle($cancelled, Staff::create(['name' => 'Ana', 'role' => StaffRole::Captain])))
        ->toThrow(InvalidBooking::class);
});
