<?php

namespace App\Models;

use App\Enums\AssetKind;
use App\Enums\Currency;
use App\Enums\ServiceCategory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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

    public function priceFor(int $adults, int $children): int
    {
        return $adults * $this->price_adult_cents
            + $children * ($this->price_child_cents ?? $this->price_adult_cents);
    }
}
