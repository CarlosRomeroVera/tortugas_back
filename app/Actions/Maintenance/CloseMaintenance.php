<?php

namespace App\Actions\Maintenance;

use App\Actions\Allocations\ReleaseAllocation;
use App\Enums\AssetKind;
use App\Enums\MaintenanceStatus;
use App\Exceptions\InvalidBooking;
use App\Models\MaintenanceOrder;
use Illuminate\Support\Facades\DB;

/**
 * Cierra la orden. Si termina antes de lo estimado, devuelve el resto del
 * horario a la agenda. Si es preventivo, reinicia el contador de servicio.
 */
final class CloseMaintenance
{
    public function __construct(private ReleaseAllocation $release) {}

    public function handle(MaintenanceOrder $order, ?float $reading = null, ?int $costCents = null): MaintenanceOrder
    {
        if (in_array($order->status, [MaintenanceStatus::Done, MaintenanceStatus::Cancelled], true)) {
            throw new InvalidBooking("La orden {$order->code} ya está cerrada.");
        }

        return DB::transaction(function () use ($order, $reading, $costCents) {
            $now = now();
            $allocation = $order->allocation;

            if ($allocation && $allocation->ends_at > $now) {
                // Acortar una ocupación nunca crea traslapes; si ni siquiera empezó, se libera.
                $allocation->starts_at >= $now
                    ? $this->release->handle($allocation)
                    : $allocation->update(['ends_at' => $now]);
            }

            $order->update([
                'status' => MaintenanceStatus::Done,
                'ends_at' => min($order->ends_at, $now),
                'reading_at_service' => $reading,
                'cost_cents' => $costCents ?? $order->cost_cents,
            ]);

            if ($reading !== null) {
                $asset = $order->asset;
                $field = $asset->kind === AssetKind::Boat ? 'engine_hours' : 'odometer_km';
                $asset->update([
                    'last_service_reading' => $reading,
                    $field => max((float) $asset->{$field}, $reading),
                ]);
            }

            return $order;
        });
    }
}
