<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\JsonResponse;

class ServiceController extends Controller
{
    /** GET /api/v1/services — catálogo de servicios activos, para no fijar slugs en la app. */
    public function __invoke(): JsonResponse
    {
        $services = Service::query()->where('is_active', true)->with('stages')->orderBy('name')->get();

        return response()->json([
            'data' => $services->map(fn (Service $s) => [
                'id' => $s->id,
                'slug' => $s->slug,
                'name' => $s->name,
                'category' => $s->category,
                'asset_kind' => $s->asset_kind,
                'shared' => $s->is_shared,
                'duration_minutes' => $s->duration_minutes,
                'capacity' => $s->default_capacity,
                'currency' => $s->currency,
                'price_adult_cents' => $s->price_adult_cents,
                'price_child_cents' => $s->price_child_cents ?? $s->price_adult_cents,
                'description' => $s->description,
                'stages' => $s->stages->map(fn ($st) => ['id' => $st->id, 'position' => $st->position, 'name' => $st->name, 'requires_reading' => $st->requires_reading]),
            ]),
        ]);
    }
}
