<?php

use App\Actions\Allocations\AllocateAsset;
use App\Actions\Allocations\ReleaseAllocation;
use App\Enums\AllocationKind;
use App\Exceptions\AssetUnavailable;
use App\Models\Asset;

beforeEach(function () {
    $this->asset = Asset::factory()->create(['name' => 'Carey I']);
    $this->allocate = app(AllocateAsset::class);
});

it('ocupa un recurso libre', function () {
    $allocation = $this->allocate->handle($this->asset, tomorrowAt('10:00'), tomorrowAt('12:00'), AllocationKind::Block);

    expect($allocation->asset_id)->toBe($this->asset->id);
});

it('rechaza una ocupación que se cruza', function (string $start, string $end) {
    $this->allocate->handle($this->asset, tomorrowAt('10:00'), tomorrowAt('12:00'), AllocationKind::Block);

    $this->allocate->handle($this->asset, tomorrowAt($start), tomorrowAt($end), AllocationKind::Block);
})->with([
    'empieza dentro' => ['11:00', '13:00'],
    'termina dentro' => ['09:00', '10:30'],
    'la contiene' => ['09:00', '13:00'],
    'idéntica' => ['10:00', '12:00'],
])->throws(AssetUnavailable::class, 'Carey I ya está ocupada');

it('permite ocupaciones contiguas', function () {
    $this->allocate->handle($this->asset, tomorrowAt('10:00'), tomorrowAt('12:00'), AllocationKind::Block);
    $this->allocate->handle($this->asset, tomorrowAt('12:00'), tomorrowAt('14:00'), AllocationKind::Block);
    $this->allocate->handle($this->asset, tomorrowAt('08:00'), tomorrowAt('10:00'), AllocationKind::Block);

    expect($this->asset->allocations()->count())->toBe(3);
});

it('ignora las ocupaciones liberadas', function () {
    $first = $this->allocate->handle($this->asset, tomorrowAt('10:00'), tomorrowAt('12:00'), AllocationKind::Block);
    app(ReleaseAllocation::class)->handle($first);

    $this->allocate->handle($this->asset, tomorrowAt('10:00'), tomorrowAt('12:00'), AllocationKind::Block);

    expect($this->asset->activeAllocations()->count())->toBe(1);
});

it('no cruza recursos distintos', function () {
    $other = Asset::factory()->create();
    $this->allocate->handle($this->asset, tomorrowAt('10:00'), tomorrowAt('12:00'), AllocationKind::Block);
    $this->allocate->handle($other, tomorrowAt('10:00'), tomorrowAt('12:00'), AllocationKind::Block);

    expect($other->allocations()->count())->toBe(1);
});

it('no vende un recurso fuera de servicio pero sí permite mantenimiento', function () {
    $broken = Asset::factory()->outOfService()->create(['name' => 'Caguama']);

    expect(fn () => $this->allocate->handle($broken, tomorrowAt('10:00'), tomorrowAt('12:00'), AllocationKind::Departure))
        ->toThrow(AssetUnavailable::class, 'Caguama está fuera de servicio');

    $this->allocate->handle($broken, tomorrowAt('10:00'), tomorrowAt('12:00'), AllocationKind::Maintenance);
    expect($broken->allocations()->count())->toBe(1);
});

it('rechaza un intervalo invertido', function () {
    $this->allocate->handle($this->asset, tomorrowAt('12:00'), tomorrowAt('10:00'), AllocationKind::Block);
})->throws(InvalidArgumentException::class);
