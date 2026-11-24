<?php

namespace App\Filament\Resources\Assets\Schemas;

use App\Enums\AllocationKind;
use App\Models\Asset;
use App\Support\LocalTime;

/** Reglas de color y texto compartidas por tabla e infolist de la flota. */
final class AssetsColors
{
    public static function forSnapshot(array $s): string
    {
        return match (true) {
            ! $s['busy'] => 'success',
            $s['kind'] === AllocationKind::Departure => 'primary',
            $s['kind'] === AllocationKind::Maintenance => 'warning',
            $s['kind'] === AllocationKind::Block => 'danger',
            default => 'gray',
        };
    }

    public static function freeAtText(array $s): string
    {
        if (! $s['busy']) {
            return $s['next_at']
                ? 'Ahora · hasta las '.$s['next_at']->tz(LocalTime::zone())->format('H:i')
                : 'Ahora';
        }

        if (! $s['free_at']) {
            return 'Sin fecha';
        }

        $local = $s['free_at']->tz(LocalTime::zone());

        return $local->isToday() ? $local->format('H:i') : $local->translatedFormat('D d M H:i');
    }

    /** Ámbar al pasar el 90 % del intervalo, rojo al cumplirlo. */
    public static function forService(Asset $asset): ?string
    {
        $next = $asset->nextServiceReading();
        $reading = $asset->currentReading();
        if (! $next || $reading === null) {
            return null;
        }

        return match (true) {
            $reading >= $next => 'danger',
            $reading >= $next - 0.1 * $asset->service_interval => 'warning',
            default => null,
        };
    }
}
