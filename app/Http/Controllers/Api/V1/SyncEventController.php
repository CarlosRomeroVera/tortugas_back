<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Field\FieldEventData;
use App\Actions\Field\RecordFieldEvent;
use App\Enums\FaultSeverity;
use App\Enums\FieldEventType;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SyncEventController extends Controller
{
    public const MAX_EVENTS = 200;

    /**
     * POST /api/v1/sync/events
     *
     * Lote de eventos guardados en el teléfono. Se aplican en el orden en que
     * ocurrieron (occurred_at), no en el que llegan. Cada uno responde por separado:
     * applied, ignored y duplicate ya se pueden borrar de la cola; rejected no
     * entrará aunque se reenvíe.
     */
    public function __invoke(Request $request, RecordFieldEvent $record): JsonResponse
    {
        $types = fn (FieldEventType ...$t) => 'required_if:events.*.type,'.implode(',', array_map(fn ($x) => $x->value, $t));

        $data = $request->validate([
            'events' => ['required', 'array', 'min:1', 'max:'.self::MAX_EVENTS],
            'events.*.client_uuid' => ['required', 'uuid', 'distinct:ignore_case'],
            'events.*.type' => ['required', Rule::enum(FieldEventType::class)],
            'events.*.occurred_at' => ['required', 'date'],
            'events.*.departure_id' => ['nullable', 'uuid', $types(FieldEventType::Stage)],
            'events.*.stage_id' => ['nullable', 'uuid', $types(FieldEventType::Stage)],
            'events.*.booking_id' => ['nullable', 'uuid', $types(FieldEventType::CheckIn, FieldEventType::CheckInUndo, FieldEventType::NoShow)],
            'events.*.asset_id' => ['nullable', 'uuid', $types(FieldEventType::Fault)],
            'events.*.severity' => ['nullable', Rule::enum(FaultSeverity::class), $types(FieldEventType::Fault)],
            'events.*.description' => ['nullable', 'string', 'max:2000', $types(FieldEventType::Fault)],
            'events.*.reading' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'events.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'events.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $events = collect($data['events'])->map(fn (array $e) => FieldEventData::fromArray($e));

        $results = $events
            ->sortBy(fn (FieldEventData $e) => $e->occurredAt->getTimestampMs())
            ->mapWithKeys(fn (FieldEventData $e) => [$e->clientUuid => $record->handle($request->user(), $e)]);

        return response()->json([
            // En el mismo orden en que se enviaron.
            'results' => $events->map(fn (FieldEventData $e) => $results[$e->clientUuid])->values(),
        ]);
    }
}
