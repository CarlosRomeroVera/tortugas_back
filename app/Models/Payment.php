<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Montos en centavos. exchange_rate = pesos por 1 USD el día del pago (se guarda
 * siempre, aunque el pago sea en MXN) para poder convertir en ambos sentidos.
 */
class Payment extends Model
{
    use HasUuids, LogsActivity;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'currency' => Currency::class,
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'exchange_rate' => 'decimal:6',
            'paid_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** Monto expresado en otra moneda usando el tipo de cambio guardado en el pago. */
    public function amountIn(Currency $currency): int
    {
        if ($currency === $this->currency) {
            return $this->amount_cents;
        }

        // Se pasa por MXN con el tipo de cambio guardado en el pago.
        return $currency === Currency::MXN
            ? $this->amount_mxn_cents
            : (int) round($this->amount_mxn_cents / (float) $this->exchange_rate);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty();
    }
}
