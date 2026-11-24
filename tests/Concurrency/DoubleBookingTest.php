<?php

/*
 * Pruebas con procesos PHP reales en paralelo contra MySQL: es la única forma de
 * comprobar que el bloqueo de fila evita reservas dobles. Usan DatabaseTruncation
 * porque los otros procesos solo ven datos ya confirmados.
 */

use App\Models\Allocation;
use App\Models\Asset;
use App\Models\Booking;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Process;

uses(DatabaseTruncation::class);

const WORKERS = 8;

function race(array $commands): array
{
    $env = [
        'APP_ENV' => 'testing',
        'DB_CONNECTION' => 'mysql',
        'DB_DATABASE' => config('database.connections.mysql.database'),
    ];

    $pool = Process::pool(function ($pool) use ($commands, $env) {
        foreach ($commands as $cmd) {
            $pool->env($env)->timeout(30)->command([PHP_BINARY, base_path('tests/Concurrency/worker.php'), ...$cmd]);
        }
    })->start();

    $outcomes = collect($pool->wait()->collect())
        ->map(fn ($r) => $r->successful() ? trim($r->output()) : 'error: '.$r->errorOutput())
        ->countBy()
        ->all();
    ksort($outcomes);

    return $outcomes;
}

it('solo una de varias ocupaciones simultáneas del mismo recurso entra', function () {
    $asset = Asset::factory()->create();
    $goAt = sprintf('%.3f', microtime(true) + 1.5);

    $results = race(array_fill(0, WORKERS, [
        'allocate', $asset->id, tomorrowAt('10:00')->toIso8601String(), tomorrowAt('12:00')->toIso8601String(), $goAt,
    ]));

    expect($results)->toBe(['ok' => 1, 'rejected' => WORKERS - 1])
        ->and(Allocation::where('asset_id', $asset->id)->count())->toBe(1);
});

it('no vende más lugares que la capacidad con reservas simultáneas', function () {
    $departure = scheduleDeparture(asset: Asset::factory()->capacity(10)->create());
    $goAt = sprintf('%.3f', microtime(true) + 1.5);

    // 8 procesos piden 3 lugares cada uno: solo caben 3 reservas (9 de 10 lugares).
    $results = race(array_fill(0, WORKERS, ['book', $departure->id, '3', $goAt]));

    expect($results)->toBe(['ok' => 3, 'rejected' => WORKERS - 3])
        ->and((int) Booking::where('departure_id', $departure->id)->sum('pax'))->toBe(9);
});
