<?php

namespace App\Actions\Payments;

use App\Enums\BookingStatus;
use App\Enums\Currency;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\InvalidBooking;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

/** Registra un cobro. El primer pago confirma la reserva. */
final class RecordPayment
{
    public function handle(
        Booking $booking,
        int $amountCents,
        Currency $currency,
        PaymentMethod $method,
        ?float $usdRate = null,
        ?string $provider = null,
        ?string $providerReference = null,
        PaymentStatus $status = PaymentStatus::Paid,
    ): Payment {
        if ($amountCents <= 0) {
            throw new InvalidBooking('El monto debe ser mayor a cero.');
        }
        if ($booking->status === BookingStatus::Cancelled) {
            throw new InvalidBooking("La reserva {$booking->code} está cancelada.");
        }

        $rate = $usdRate ?? config('tortugas.default_usd_rate');

        return DB::transaction(function () use ($booking, $amountCents, $currency, $method, $rate, $provider, $providerReference, $status) {
            $payment = $booking->payments()->create([
                'amount_cents' => $amountCents,
                'currency' => $currency,
                'exchange_rate' => $rate,
                'amount_mxn_cents' => $currency === Currency::MXN ? $amountCents : (int) round($amountCents * $rate),
                'method' => $method,
                'status' => $status,
                'provider' => $provider,
                'provider_reference' => $providerReference,
                'paid_at' => $status === PaymentStatus::Paid ? now() : null,
                'recorded_by' => auth()->id(),
            ]);

            if ($status === PaymentStatus::Paid && $booking->status === BookingStatus::Pending) {
                $booking->update(['status' => BookingStatus::Confirmed]);
            }

            return $payment;
        });
    }
}
