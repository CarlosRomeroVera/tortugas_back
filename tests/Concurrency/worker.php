<?php

/*
 * Proceso independiente para las pruebas de concurrencia.
 * Espera hasta el instante indicado para que todos los procesos compitan a la vez.
 *
 *   php worker.php allocate <asset_id> <start_iso> <end_iso> <go_at>
 *   php worker.php book <departure_id> <adults> <go_at>
 */

use App\Actions\Allocations\AllocateAsset;
use App\Actions\Bookings\BookingData;
use App\Actions\Bookings\CreateBooking;
use App\Enums\AllocationKind;
use App\Exceptions\DomainException;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$mode = $argv[1];
$goAt = (float) end($argv);

try {
    DB::connection()->getPdo(); // conectar antes de la señal de salida
    time_sleep_until($goAt);

    match ($mode) {
        'allocate' => app(AllocateAsset::class)->handle(
            $argv[2], CarbonImmutable::parse($argv[3]), CarbonImmutable::parse($argv[4]), AllocationKind::Block, null, 'prueba de concurrencia',
        ),
        'book' => app(CreateBooking::class)->handle(new BookingData(
            customer: ['name' => 'Concurrencia'], adults: (int) $argv[3], departureId: $argv[2],
        )),
    };
    echo 'ok';
} catch (DomainException) {
    echo 'rejected';
}
