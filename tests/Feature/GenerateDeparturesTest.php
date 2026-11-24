<?php

use App\Models\Asset;
use App\Models\Departure;
use App\Models\Service;
use Carbon\CarbonImmutable;

it('genera las salidas de los horarios fijos sin duplicarlas', function () {
    $service = Service::factory()->create();
    $boat = Asset::factory()->create();
    foreach (range(0, 6) as $weekday) {
        $service->schedules()->create(['asset_id' => $boat->id, 'weekday' => $weekday, 'departs_at' => '08:30:00']);
        $service->schedules()->create(['asset_id' => $boat->id, 'weekday' => $weekday, 'departs_at' => '11:30:00']);
    }
    $from = CarbonImmutable::tomorrow('America/Cancun')->toDateString();

    $this->artisan('departures:generate', ['--days' => 3, '--from' => $from])->assertSuccessful();
    $this->artisan('departures:generate', ['--days' => 3, '--from' => $from])->assertSuccessful();

    expect(Departure::count())->toBe(6)
        ->and(Departure::orderBy('starts_at')->first()->starts_at->tz('America/Cancun')->format('Y-m-d H:i'))
        ->toBe("{$from} 08:30");
});
