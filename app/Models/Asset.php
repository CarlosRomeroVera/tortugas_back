<?php

namespace App\Models;

use App\Enums\AllocationKind;
use App\Enums\AllocationStatus;
use App\Enums\AssetKind;
use App\Enums\AssetStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Embarcación o vehículo. */
class Asset extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'kind' => AssetKind::class,
            'status' => AssetStatus::class,
            'engine_hours' => 'decimal:1',
            'last_service_reading' => 'decimal:1',
        ];
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(Allocation::class);
    }

    public function activeAllocations(): HasMany
    {
        return $this->allocations()->where('status', AllocationStatus::Active);
    }

    /** Ocupaciones activas que aún no terminan, en orden. */
    public function upcomingAllocations(): HasMany
    {
        return $this->activeAllocations()->where('ends_at', '>', now())->orderBy('starts_at');
    }

    /**
     * Qué hace ahora y a qué hora queda libre. Usa upcomingAllocations ya cargadas
     * para no consultar fila por fila en las tablas.
     *
     * @return array{busy: bool, kind: ?AllocationKind, label: string, free_at: ?CarbonInterface, next_at: ?CarbonInterface}
     */
    public function snapshot(?CarbonInterface $now = null): array
    {
        $now ??= now();
        $allocations = $this->relationLoaded('upcomingAllocations')
            ? $this->upcomingAllocations
            : $this->upcomingAllocations()->get();

        $current = $allocations->first(fn (Allocation $a) => $a->starts_at <= $now && $a->ends_at > $now);

        if ($this->status !== AssetStatus::Active && ! $current) {
            return ['busy' => true, 'kind' => null, 'label' => $this->status->getLabel(), 'free_at' => null, 'next_at' => null];
        }

        if (! $current) {
            $next = $allocations->first(fn (Allocation $a) => $a->starts_at > $now);

            return ['busy' => false, 'kind' => null, 'label' => 'Libre', 'free_at' => null, 'next_at' => $next?->starts_at];
        }

        // Si hay ocupaciones pegadas una tras otra, queda libre al terminar la última.
        $freeAt = $current->ends_at;
        foreach ($allocations as $a) {
            if ($a->starts_at <= $freeAt && $a->ends_at > $freeAt) {
                $freeAt = $a->ends_at;
            }
        }

        return [
            'busy' => true,
            'kind' => $current->kind,
            'label' => $current->kind->getLabel(),
            'free_at' => $freeAt,
            'next_at' => null,
        ];
    }

    public function departures(): HasMany
    {
        return $this->hasMany(Departure::class);
    }

    public function maintenanceOrders(): HasMany
    {
        return $this->hasMany(MaintenanceOrder::class);
    }

    public function faultReports(): HasMany
    {
        return $this->hasMany(FaultReport::class);
    }

    /** Campo donde se guarda la lectura: horas de motor o kilómetros. */
    public function readingField(): string
    {
        return $this->kind === AssetKind::Boat ? 'engine_hours' : 'odometer_km';
    }

    public function scopeBoats(Builder $query): void
    {
        $query->where('kind', AssetKind::Boat);
    }

    public function scopeVehicles(Builder $query): void
    {
        $query->where('kind', AssetKind::Vehicle);
    }

    /** Lectura actual (horas de motor o km) según el tipo. */
    public function currentReading(): ?float
    {
        return $this->kind === AssetKind::Boat
            ? ($this->engine_hours !== null ? (float) $this->engine_hours : null)
            : $this->odometer_km;
    }

    /** Lectura en la que toca el siguiente servicio preventivo. */
    public function nextServiceReading(): ?float
    {
        if (! $this->service_interval) {
            return null;
        }

        return (float) ($this->last_service_reading ?? 0) + $this->service_interval;
    }
}
