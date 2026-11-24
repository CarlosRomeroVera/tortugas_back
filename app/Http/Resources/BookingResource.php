<?php

namespace App\Http\Resources;

use App\Models\Booking;
use App\Support\LocalTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Booking */
class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'status' => $this->status,
            'channel' => $this->channel,
            'adults' => $this->adults,
            'children' => $this->children,
            'pax' => $this->pax,
            'currency' => $this->currency,
            'total_cents' => $this->total_cents,
            'paid_cents' => $this->paidCents(),
            'balance_cents' => $this->balanceCents(),
            'cruise_ship' => $this->cruise_ship,
            'all_aboard_at' => $this->all_aboard_at?->toIso8601String(),
            'customer' => [
                'name' => $this->customer->name,
                'email' => $this->customer->email,
                'phone' => $this->customer->phone,
            ],
            'departure' => [
                'id' => $this->departure->id,
                'code' => $this->departure->code,
                'service' => $this->departure->service->name,
                'asset' => $this->departure->asset->name,
                'starts_at' => $this->departure->starts_at->toIso8601String(),
                'local_time' => $this->departure->starts_at->tz(LocalTime::zone())->format('Y-m-d H:i'),
            ],
            'payments' => $this->payments->map(fn ($p) => [
                'amount_cents' => $p->amount_cents,
                'currency' => $p->currency,
                'method' => $p->method,
                'status' => $p->status,
                'paid_at' => $p->paid_at?->toIso8601String(),
            ]),
        ];
    }
}
