<?php

namespace App\Actions\Bookings;

use App\Actions\Departures\CancelDeparture;
use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Support\Facades\DB;

/** Cancela la reserva y libera su lugar. Si era un servicio privado, también libera la embarcación. */
final class CancelBooking
{
    public function handle(Booking $booking, ?string $reason = null, bool $releaseDeparture = true): Booking
    {
        return DB::transaction(function () use ($booking, $reason, $releaseDeparture) {
            $booking->update([
                'status' => BookingStatus::Cancelled,
                'cancelled_at' => now(),
                'notes' => trim(($booking->notes ?? '')."\nCancelada: ".($reason ?? 'sin motivo')),
            ]);

            $departure = $booking->departure;
            if ($releaseDeparture && ! $departure->service->is_shared
                && $departure->bookings()->occupyingSeats()->doesntExist()) {
                app(CancelDeparture::class)->handle($departure, $reason);
            }

            return $booking;
        });
    }
}
