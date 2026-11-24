<?php

namespace App\Actions\Bookings;

use App\Enums\SalesChannel;
use Carbon\CarbonImmutable;

/**
 * Datos para crear una reserva.
 * - Salida compartida (snorkel): indica departureId.
 * - Servicio privado: indica serviceId + startsAt (UTC) y opcionalmente assetId;
 *   la Action crea la salida y ocupa el recurso.
 */
final readonly class BookingData
{
    public function __construct(
        public array $customer, // name, email?, phone?, country?
        public int $adults,
        public int $children = 0,
        public SalesChannel $channel = SalesChannel::Counter,
        public ?string $departureId = null,
        public ?string $serviceId = null,
        public ?CarbonImmutable $startsAt = null,
        public ?string $assetId = null,
        public ?string $agencyReference = null,
        public ?string $cruiseShip = null,
        public ?CarbonImmutable $allAboardAt = null,
        public ?string $pickupLocationId = null,
        public ?string $notes = null,
        public ?string $idempotencyKey = null,
    ) {}

    public function pax(): int
    {
        return $this->adults + $this->children;
    }
}
