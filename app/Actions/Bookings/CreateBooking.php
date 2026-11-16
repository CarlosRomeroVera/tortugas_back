<?php

namespace App\Actions\Bookings;

use App\Actions\Departures\ScheduleDeparture;
use App\Enums\BookingStatus;
use App\Enums\DepartureStatus;
use App\Exceptions\AssetUnavailable;
use App\Exceptions\InvalidBooking;
use App\Exceptions\NoSeatsLeft;
use App\Models\Asset;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Departure;
use App\Models\Service;
use App\Support\Codes;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Crea una reserva sin vender de más.
 *
 * El cupo de una salida compartida se protege bloqueando la fila de la salida
 * mientras se cuentan los lugares ocupados. Un servicio privado crea su propia
 * salida con ScheduleDeparture, que ocupa el recurso completo.
 */
final class CreateBooking
{
    public function __construct(private ScheduleDeparture $scheduleDeparture) {}

    public function handle(BookingData $data): Booking
    {
        if ($data->idempotencyKey && ($existing = $this->findByKey($data->idempotencyKey))) {
            return $existing;
        }

        if ($data->adults < 1) {
            throw new InvalidBooking('La reserva necesita al menos un adulto.');
        }

        try {
            return DB::transaction(fn () => $this->create($data), attempts: 3);
        } catch (QueryException $e) {
            // Dos peticiones con la misma clave al mismo tiempo: gana la primera.
            if ($data->idempotencyKey && ($e->errorInfo[1] ?? null) === 1062
                && ($existing = $this->findByKey($data->idempotencyKey))) {
                return $existing;
            }
            throw $e;
        }
    }

    private function create(BookingData $data): Booking
    {
        $departure = $data->departureId
            ? Departure::query()->whereKey($data->departureId)->lockForUpdate()->firstOrFail()
            : $this->schedulePrivateDeparture($data);

        if ($departure->status !== DepartureStatus::Scheduled) {
            throw new InvalidBooking("La salida {$departure->code} ya no admite reservas ({$departure->status->getLabel()}).");
        }

        $left = $departure->seatsLeft();
        if ($data->pax() > $left) {
            throw NoSeatsLeft::for($data->pax(), $left);
        }

        $service = $departure->service;

        return Booking::create([
            'code' => Codes::make('R'),
            'departure_id' => $departure->getKey(),
            'customer_id' => $this->resolveCustomer($data->customer)->getKey(),
            'channel' => $data->channel,
            'agency_reference' => $data->agencyReference,
            'adults' => $data->adults,
            'children' => $data->children,
            'pax' => $data->pax(),
            'status' => BookingStatus::Pending,
            'total_cents' => $service->priceFor($data->adults, $data->children),
            'currency' => $service->currency,
            'cruise_ship' => $data->cruiseShip,
            'all_aboard_at' => $data->allAboardAt,
            'pickup_location_id' => $data->pickupLocationId,
            'idempotency_key' => $data->idempotencyKey,
            'notes' => $data->notes,
            'created_by' => auth()->id(),
        ]);
    }

    private function schedulePrivateDeparture(BookingData $data): Departure
    {
        if (! $data->serviceId || ! $data->startsAt) {
            throw new InvalidBooking('Indica la salida, o el servicio y la hora de inicio.');
        }

        $service = Service::query()->where('is_active', true)->findOrFail($data->serviceId);

        if ($service->is_shared) {
            throw new InvalidBooking("{$service->name} es una salida compartida: elige uno de sus horarios.");
        }

        $candidates = $data->assetId
            ? Asset::query()->whereKey($data->assetId)->get()
            : $service->eligibleAssets()
                ->where('capacity', '>=', $data->pax())
                ->orderBy('capacity') // la más chica que alcance
                ->get();

        foreach ($candidates as $asset) {
            try {
                // Savepoint propio: si esta unidad está ocupada, se prueba la siguiente.
                $departure = DB::transaction(
                    fn () => $this->scheduleDeparture->handle($service, $asset, $data->startsAt)
                );

                return Departure::query()->whereKey($departure->getKey())->lockForUpdate()->firstOrFail();
            } catch (AssetUnavailable $e) {
                if ($data->assetId) {
                    throw $e;
                }
            }
        }

        throw AssetUnavailable::noneFree();
    }

    private function resolveCustomer(array $customer): Customer
    {
        $email = $customer['email'] ?? null;

        return $email
            ? Customer::query()->firstOrCreate(['email' => $email], $customer)
            : Customer::create($customer);
    }

    private function findByKey(string $key): ?Booking
    {
        return Booking::query()->where('idempotency_key', $key)->first();
    }
}
