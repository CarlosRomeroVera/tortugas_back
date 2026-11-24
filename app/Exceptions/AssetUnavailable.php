<?php

namespace App\Exceptions;

use Carbon\Carbon;
use DateTimeInterface;

class AssetUnavailable extends DomainException
{
    public static function overlapping(string $assetName, DateTimeInterface $start, DateTimeInterface $end): self
    {
        $tz = config('tortugas.display_timezone');

        return new self(sprintf(
            '%s ya está ocupada entre %s y %s.',
            $assetName,
            Carbon::instance($start)->tz($tz)->format('d/m H:i'),
            Carbon::instance($end)->tz($tz)->format('H:i'),
        ));
    }

    public static function outOfService(string $assetName): self
    {
        return new self("{$assetName} está fuera de servicio.");
    }

    public static function noneFree(): self
    {
        return new self('No hay ninguna unidad libre con esa capacidad en ese horario.');
    }
}
