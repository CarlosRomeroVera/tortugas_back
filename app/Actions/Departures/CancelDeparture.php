<?php

namespace App\Actions\Departures;

use App\Actions\Allocations\ReleaseAllocation;
use App\Actions\Bookings\CancelBooking;
use App\Enums\DepartureStatus;
use App\Models\Departure;
use Illuminate\Support\Facades\DB;

/** Cancela la salida, sus reservas activas y libera el recurso. */
final class CancelDeparture
{
    public function __construct(
        private ReleaseAllocation $release,
        private CancelBooking $cancelBooking,
    ) {}

    public function handle(Departure $departure, ?string $reason = null): Departure
    {
        return DB::transaction(function () use ($departure, $reason) {
            $departure->bookings()->occupyingSeats()->get()
                ->each(fn ($booking) => $this->cancelBooking->handle($booking, $reason, releaseDeparture: false));

            $this->release->handle($departure->allocation);

            $departure->update([
                'status' => DepartureStatus::Cancelled,
                'notes' => trim(($departure->notes ?? '')."\nCancelada: ".($reason ?? 'sin motivo')),
            ]);

            return $departure;
        });
    }
}
