<?php

namespace App\Models;

use App\Enums\AssetKind;
use App\Enums\AssetStatus;
use App\Enums\Currency;
use App\Enums\ServiceCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'category' => ServiceCategory::class,
            'asset_kind' => AssetKind::class,
            'currency' => Currency::class,
            'is_shared' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function stages(): HasMany
    {
        return $this->hasMany(ServiceStage::class)->orderBy('position');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(ServiceSchedule::class);
    }

    public function departures(): HasMany
    {
        return $this->hasMany(Departure::class);
    }

    /** Unidades habilitadas. Vacío = cualquier unidad de su tipo. */
    public function assets(): BelongsToMany
    {
        return $this->belongsToMany(Asset::class);
    }

    /** Unidades activas que pueden dar este servicio. */
    public function eligibleAssets(): Builder
    {
        $query = Asset::query()->where('kind', $this->asset_kind)->where('status', AssetStatus::Active);
        $ids = $this->assets()->pluck('assets.id');

        return $ids->isEmpty() ? $query : $query->whereIn('id', $ids);
    }

    public function allowsAsset(Asset $asset): bool
    {
        if ($asset->kind !== $this->asset_kind) {
            return false;
        }

        $ids = $this->assets()->pluck('assets.id');

        return $ids->isEmpty() || $ids->contains($asset->getKey());
    }

    public function priceFor(int $adults, int $children): int
    {
        return $adults * $this->price_adult_cents
            + $children * ($this->price_child_cents ?? $this->price_adult_cents);
    }
}
