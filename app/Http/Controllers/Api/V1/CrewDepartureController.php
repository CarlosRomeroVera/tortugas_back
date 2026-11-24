<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CrewDepartureResource;
use App\Models\Departure;
use App\Support\LocalTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Salidas del capitán o chofer que hace la petición. Solo ve aquellas donde va como tripulación. */
class CrewDepartureController extends Controller
{
    /** GET /api/v1/me/departures?date=2026-11-16 (fecha local; por omisión, hoy en Cozumel). */
    public function index(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);
        $this->requireStaff($request);

        [$from, $to] = LocalTime::dayBounds($data['date'] ?? LocalTime::today());

        $departures = $this->query($request)
            ->where('starts_at', '>=', $from)
            ->where('starts_at', '<', $to)
            ->orderBy('starts_at')
            ->get();

        return CrewDepartureResource::collection($departures);
    }

    /** GET /api/v1/me/departures/{id} — con etapas y manifiesto de pasajeros. */
    public function show(Request $request, string $departure): CrewDepartureResource
    {
        $this->requireStaff($request);

        $model = $this->query($request)
            ->with([
                'service.stages',
                'fieldEvents' => fn ($q) => $q->where('type', 'stage')->where('outcome', 'applied'),
                'bookings.customer',
                'bookings.pickupLocation',
                'bookings.payments',
            ])
            ->findOrFail($departure);

        return CrewDepartureResource::make($model)->withManifest();
    }

    private function query(Request $request): Builder
    {
        return Departure::query()
            ->crewedBy($request->user())
            ->with(['service.stages', 'asset', 'currentStage', 'crew', 'bookings']);
    }

    private function requireStaff(Request $request): void
    {
        abort_unless($request->user()->staff()->exists(), 403, 'Tu usuario no está ligado a una ficha de personal. Pide a coordinación que lo vincule.');
    }
}
