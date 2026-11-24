<?php

namespace App\Models;

use App\Enums\StaffRole;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Staff extends Model
{
    use HasUuids;

    protected $table = 'staff';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'role' => StaffRole::class,
            'license_expires_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function departures(): BelongsToMany
    {
        return $this->belongsToMany(Departure::class, 'departure_crew')->withPivot('role');
    }
}
