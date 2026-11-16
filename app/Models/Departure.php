<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\DepartureStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/** Una salida concreta de un servicio en una embarcación o vehículo. */
class Departure extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => DepartureStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'departed_at' => 'datetime',
            'returned_at' => 'datetime',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function currentStage(): BelongsTo
    {
        return $this->belongsTo(ServiceStage::class, 'current_stage_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function allocation(): MorphOne
    {
        return $this->morphOne(Allocation::class, 'allocatable');
    }

    public function crew(): BelongsToMany
    {
        return $this->belongsToMany(Staff::class, 'departure_crew')->withPivot('role');
    }

    /** Personas que ya ocupan lugar (reservas pendientes, confirmadas o completadas). */
    public function seatsTaken(): int
    {
        return (int) $this->bookings()
            ->whereIn('status', BookingStatus::occupyingSeats())
            ->sum('pax');
    }

    public function seatsLeft(): int
    {
        return max(0, $this->capacity - $this->seatsTaken());
    }
}
