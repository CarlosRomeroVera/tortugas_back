<?php

namespace App\Models;

use App\Enums\FieldEventType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Evento recibido de la app. Solo se insertan; nunca se editan. */
class FieldEvent extends Model
{
    use HasUuids;

    public const APPLIED = 'applied';

    public const IGNORED = 'ignored';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => FieldEventType::class,
            'reading' => 'decimal:1',
            'latitude' => 'decimal:6',
            'longitude' => 'decimal:6',
            'occurred_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    public function departure(): BelongsTo
    {
        return $this->belongsTo(Departure::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
