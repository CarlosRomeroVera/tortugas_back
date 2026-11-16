<?php

namespace App\Models;

use App\Enums\LocationType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Hoteles, muelles, terminal de cruceros, ferry. */
class Location extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => LocationType::class,
            'latitude' => 'decimal:6',
            'longitude' => 'decimal:6',
        ];
    }
}
