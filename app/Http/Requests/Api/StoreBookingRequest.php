<?php

namespace App\Http\Requests\Api;

use App\Actions\Bookings\BookingData;
use App\Enums\SalesChannel;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasAnyRole(['admin', 'coordinacion', 'ventas']);
    }

    public function rules(): array
    {
        return [
            'departure_id' => ['required_without:service_id', 'nullable', 'uuid', 'exists:departures,id'],
            'service_id' => ['required_without:departure_id', 'nullable', 'uuid', 'exists:services,id'],
            'starts_at' => ['required_with:service_id', 'nullable', 'date'],
            'asset_id' => ['nullable', 'uuid', 'exists:assets,id'],
            'adults' => ['required', 'integer', 'min:1', 'max:100'],
            'children' => ['nullable', 'integer', 'min:0', 'max:100'],
            'channel' => ['required', Rule::enum(SalesChannel::class)],
            'agency_reference' => ['nullable', 'string', 'max:100'],
            'customer.name' => ['required', 'string', 'max:150'],
            'customer.email' => ['nullable', 'email', 'max:150'],
            'customer.phone' => ['nullable', 'string', 'max:30'],
            'customer.country' => ['nullable', 'string', 'size:2'],
            'cruise_ship' => ['nullable', 'string', 'max:100'],
            'all_aboard_at' => ['nullable', 'date'],
            'pickup_location_id' => ['nullable', 'uuid', 'exists:locations,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function toData(): BookingData
    {
        $v = $this->validated();

        return new BookingData(
            customer: array_filter($v['customer'], fn ($x) => $x !== null),
            adults: (int) $v['adults'],
            children: (int) ($v['children'] ?? 0),
            channel: SalesChannel::from($v['channel']),
            departureId: $v['departure_id'] ?? null,
            serviceId: $v['service_id'] ?? null,
            startsAt: isset($v['starts_at']) ? CarbonImmutable::parse($v['starts_at'])->utc() : null,
            assetId: $v['asset_id'] ?? null,
            agencyReference: $v['agency_reference'] ?? null,
            cruiseShip: $v['cruise_ship'] ?? null,
            allAboardAt: isset($v['all_aboard_at']) ? CarbonImmutable::parse($v['all_aboard_at'])->utc() : null,
            pickupLocationId: $v['pickup_location_id'] ?? null,
            notes: $v['notes'] ?? null,
            idempotencyKey: $this->header('Idempotency-Key'),
        );
    }
}
