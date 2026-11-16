<?php

use App\Actions\Departures\ScheduleDeparture;
use App\Enums\StaffRole;
use App\Models\Asset;
use App\Models\Departure;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Concurrency');

/** Instante UTC a partir de una hora "HH:MM" de mañana en Cozumel. */
function tomorrowAt(string $time): CarbonImmutable
{
    return CarbonImmutable::parse('tomorrow '.$time, 'America/Cancun')->utc();
}

function scheduleDeparture(?Service $service = null, ?Asset $asset = null, string $at = '10:00', ?int $capacity = null): Departure
{
    $service ??= Service::factory()->create();
    $asset ??= Asset::factory()->create();

    return app(ScheduleDeparture::class)->handle($service, $asset, tomorrowAt($at), $capacity);
}

function userWithRole(string $role): User
{
    (new RoleSeeder)->run();

    return tap(User::factory()->create())->assignRole($role);
}

/** Usuario de la app ligado a una ficha de personal. */
function crewUser(string $role = 'capitan', StaffRole $staffRole = StaffRole::Captain, string $name = 'Rubén Canul'): User
{
    $user = userWithRole($role);
    Staff::create(['user_id' => $user->id, 'name' => $name, 'role' => $staffRole]);

    return $user->fresh();
}

/** Servicio con etapas; la última pide lectura de horómetro u odómetro. */
function serviceWithStages(array $stages = ['Check-in', 'Zarpe', 'Arrecife', 'Cierre'], array $attrs = []): Service
{
    $service = Service::factory()->create($attrs);
    foreach ($stages as $i => $name) {
        $service->stages()->create(['position' => $i + 1, 'name' => $name, 'requires_reading' => $i === count($stages) - 1]);
    }

    return $service->load('stages');
}
