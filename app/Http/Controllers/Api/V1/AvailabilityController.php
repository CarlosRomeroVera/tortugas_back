<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\Availability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AvailabilityController extends Controller
{
    /** GET /api/v1/availability?service={slug|id}&date=2026-11-15&pax=4 */
    public function __invoke(Request $request, Availability $availability): JsonResponse
    {
        $data = $request->validate([
            'service' => ['required', 'string'],
            'date' => ['required', 'date_format:Y-m-d'],
            'pax' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $service = Service::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('slug', $data['service'])->orWhere('id', $data['service']))
            ->firstOrFail();

        return response()->json([
            'service' => [
                'id' => $service->id,
                'slug' => $service->slug,
                'name' => $service->name,
                'shared' => $service->is_shared,
                'duration_minutes' => $service->duration_minutes,
                'currency' => $service->currency,
                'price_adult_cents' => $service->price_adult_cents,
                'price_child_cents' => $service->price_child_cents,
            ],
            'date' => $data['date'],
            'timezone' => config('tortugas.display_timezone'),
            'slots' => $availability->for($service, $data['date'], (int) $data['pax']),
        ]);
    }
}
