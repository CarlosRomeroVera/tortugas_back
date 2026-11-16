<?php

namespace App\Actions\Allocations;

use App\Enums\AllocationKind;
use App\Enums\AllocationStatus;
use App\Enums\AssetStatus;
use App\Exceptions\AssetUnavailable;
use App\Models\Allocation;
use App\Models\Asset;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * ÚNICA forma de ocupar una embarcación o vehículo.
 *
 * MySQL no tiene restricciones de exclusión, así que el no traslape se garantiza así:
 * 1. Se bloquea la fila del recurso (SELECT … FOR UPDATE).
 * 2. Con el bloqueo tomado, se busca una ocupación activa que se cruce.
 * 3. Si no hay, se inserta. Una segunda petición simultánea espera en el paso 1
 *    y, cuando avanza, ya ve la ocupación de la primera.
 *
 * Si llamas a esta Action dentro de otra transacción, el bloqueo dura hasta que
 * termine la transacción externa, que es lo que se quiere.
 */
final class AllocateAsset
{
    public function handle(
        Asset|string $asset,
        DateTimeInterface $start,
        DateTimeInterface $end,
        AllocationKind $kind,
        ?Model $allocatable = null,
        ?string $reason = null,
    ): Allocation {
        if ($end <= $start) {
            throw new InvalidArgumentException('La hora de fin debe ser posterior a la de inicio.');
        }

        $assetId = $asset instanceof Asset ? $asset->getKey() : $asset;

        return DB::transaction(function () use ($assetId, $start, $end, $kind, $allocatable, $reason) {
            /** @var Asset $locked */
            $locked = Asset::query()->whereKey($assetId)->lockForUpdate()->firstOrFail();

            if ($kind !== AllocationKind::Maintenance && $locked->status !== AssetStatus::Active) {
                throw AssetUnavailable::outOfService($locked->name);
            }

            $clash = Allocation::query()
                ->where('asset_id', $locked->getKey())
                ->active()
                ->overlapping($start, $end)
                ->exists();

            if ($clash) {
                throw AssetUnavailable::overlapping($locked->name, $start, $end);
            }

            return Allocation::create([
                'asset_id' => $locked->getKey(),
                'starts_at' => $start,
                'ends_at' => $end,
                'kind' => $kind,
                'status' => AllocationStatus::Active,
                'allocatable_type' => $allocatable?->getMorphClass(),
                'allocatable_id' => $allocatable?->getKey(),
                'reason' => $reason,
                'created_by' => auth()->id(),
            ]);
        }, attempts: 3);
    }
}
