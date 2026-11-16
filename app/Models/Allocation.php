<?php

namespace App\Models;

use App\Enums\AllocationKind;
use App\Enums\AllocationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Ocupación de un recurso. Créala SIEMPRE con App\Actions\Allocations\AllocateAsset:
 * es la única ruta que evita traslapes.
 */
class Allocation extends Model
{
    use HasUuids, LogsActivity;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'kind' => AllocationKind::class,
            'status' => AllocationStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function allocatable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('status', AllocationStatus::Active);
    }

    /** Intervalos semiabiertos [inicio, fin): terminar a las 10:00 no choca con empezar a las 10:00. */
    public function scopeOverlapping(Builder $query, \DateTimeInterface $start, \DateTimeInterface $end): void
    {
        $query->where('starts_at', '<', $end)->where('ends_at', '>', $start);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty();
    }
}
