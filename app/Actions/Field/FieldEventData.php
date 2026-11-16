<?php

namespace App\Actions\Field;

use App\Enums\FaultSeverity;
use App\Enums\FieldEventType;
use Carbon\CarbonImmutable;

/** Un evento tal como lo manda la app. occurred_at es la hora del teléfono, en UTC. */
final readonly class FieldEventData
{
    public function __construct(
        public string $clientUuid,
        public FieldEventType $type,
        public CarbonImmutable $occurredAt,
        public ?string $departureId = null,
        public ?string $stageId = null,
        public ?string $bookingId = null,
        public ?string $assetId = null,
        public ?float $reading = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
        public ?FaultSeverity $severity = null,
        public ?string $description = null,
    ) {}

    public static function fromArray(array $e): self
    {
        // Un reloj adelantado no puede registrar algo "en el futuro".
        $occurredAt = CarbonImmutable::parse($e['occurred_at'])->utc();
        $now = CarbonImmutable::now('UTC');

        return new self(
            clientUuid: strtolower($e['client_uuid']),
            type: FieldEventType::from($e['type']),
            occurredAt: $occurredAt->greaterThan($now) ? $now : $occurredAt,
            departureId: $e['departure_id'] ?? null,
            stageId: $e['stage_id'] ?? null,
            bookingId: $e['booking_id'] ?? null,
            assetId: $e['asset_id'] ?? null,
            reading: isset($e['reading']) ? (float) $e['reading'] : null,
            latitude: isset($e['latitude']) ? (float) $e['latitude'] : null,
            longitude: isset($e['longitude']) ? (float) $e['longitude'] : null,
            severity: isset($e['severity']) ? FaultSeverity::from($e['severity']) : null,
            description: $e['description'] ?? null,
        );
    }
}
