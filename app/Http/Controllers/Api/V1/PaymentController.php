<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Payments\RecordPayment;
use App\Enums\Currency;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function store(Request $request, Booking $booking, RecordPayment $record): JsonResponse
    {
        abort_unless($request->user()->hasAnyRole(['admin', 'coordinacion', 'ventas']), 403);

        $data = $request->validate([
            'amount_cents' => ['required', 'integer', 'min:1'],
            'currency' => ['required', Rule::enum(Currency::class)],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'usd_rate' => ['nullable', 'numeric', 'min:1', 'max:100'],
            'provider' => ['nullable', 'string', 'max:50'],
            'provider_reference' => ['nullable', 'string', 'max:150'],
        ]);

        $record->handle(
            $booking,
            $data['amount_cents'],
            Currency::from($data['currency']),
            PaymentMethod::from($data['method']),
            isset($data['usd_rate']) ? (float) $data['usd_rate'] : null,
            $data['provider'] ?? null,
            $data['provider_reference'] ?? null,
        );

        return BookingResource::make($booking->fresh(['departure.service', 'departure.asset', 'customer', 'payments']))
            ->response()->setStatusCode(201);
    }
}
