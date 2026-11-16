<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\Currency;
use App\Enums\PaymentStatus;
use App\Enums\SalesChannel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Booking extends Model
{
    use HasUuids, LogsActivity;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'channel' => SalesChannel::class,
            'currency' => Currency::class,
            'all_aboard_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function departure(): BelongsTo
    {
        return $this->belongsTo(Departure::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function pickupLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'pickup_location_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function scopeOccupyingSeats(Builder $query): void
    {
        $query->whereIn('status', BookingStatus::occupyingSeats());
    }

    /** Cobrado, convertido a la moneda de la reserva. */
    public function paidCents(): int
    {
        return (int) $this->payments
            ->where('status', PaymentStatus::Paid)
            ->sum(fn (Payment $p) => $p->amountIn($this->currency));
    }

    public function balanceCents(): int
    {
        return max(0, $this->total_cents - $this->paidCents());
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty();
    }
}
