<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Bookings\CancelBooking;
use App\Actions\Bookings\CreateBooking;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    /**
     * POST /api/v1/bookings
     * Envía el header Idempotency-Key: si la petición se repite, devuelve la misma reserva (200 en vez de 201).
     */
    public function store(StoreBookingRequest $request, CreateBooking $create): JsonResponse
    {
        abort_if(strlen((string) $request->header('Idempotency-Key')) > 100, 422, 'Idempotency-Key admite máximo 100 caracteres.');

        $booking = $create->handle($request->toData());

        return BookingResource::make($booking->load(['departure.service', 'departure.asset', 'customer', 'payments']))
            ->response()
            ->setStatusCode($booking->wasRecentlyCreated ? 201 : 200);
    }

    public function show(Booking $booking): BookingResource
    {
        $this->authorizeOffice(request());

        return BookingResource::make($booking->load(['departure.service', 'departure.asset', 'customer', 'payments']));
    }

    public function cancel(Request $request, Booking $booking, CancelBooking $cancel): BookingResource
    {
        $this->authorizeOffice($request);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        return BookingResource::make($cancel->handle($booking, $data['reason'] ?? null)->fresh(['departure.service', 'departure.asset', 'customer', 'payments']));
    }

    private function authorizeOffice(Request $request): void
    {
        abort_unless($request->user()->hasAnyRole(['admin', 'coordinacion', 'ventas']), 403);
    }
}
