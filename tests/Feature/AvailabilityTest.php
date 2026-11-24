<?php

use App\Actions\Allocations\AllocateAsset;
use App\Enums\AllocationKind;
use App\Models\Asset;
use App\Models\Service;
use App\Services\Availability;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->date = CarbonImmutable::tomorrow('America/Cancun')->toDateString();
    $this->now = CarbonImmutable::now();
});

it('lista las salidas compartidas con lugares restantes', function () {
    $service = Service::factory()->create();
    $boat = Asset::factory()->capacity(10)->create();
    scheduleDeparture($service, $boat, '09:00');
    scheduleDeparture($service, $boat, '13:00');

    $slots = app(Availability::class)->for($service, $this->date, 4, $this->now);

    expect($slots)->toHaveCount(2)
        ->and($slots[0])->toMatchArray(['local_time' => '09:00', 'seats_left' => 10, 'available' => true]);
});

it('ofrece horarios privados solo donde la embarcación está libre', function () {
    config(['tortugas.operating_hours' => ['start' => '08:00', 'end' => '14:00'], 'tortugas.slot_step_minutes' => 60]);
    $service = Service::factory()->private()->create(['duration_minutes' => 120, 'buffer_minutes' => 0]);
    $boat = Asset::factory()->capacity(8)->create();

    app(AllocateAsset::class)->handle($boat, tomorrowAt('10:00'), tomorrowAt('11:00'), AllocationKind::Block);

    $times = app(Availability::class)->for($service, $this->date, 4, $this->now)->pluck('local_time')->all();

    // 08:00 termina 10:00 (libre); 09:00 y 10:00 chocan; 11:00 y 12:00 caben antes de las 14:00
    expect($times)->toBe(['08:00', '11:00', '12:00']);
});

it('no ofrece embarcaciones sin capacidad suficiente', function () {
    $service = Service::factory()->private()->create();
    Asset::factory()->capacity(6)->create();

    expect(app(Availability::class)->for($service, $this->date, 10, $this->now))->toBeEmpty();
});
