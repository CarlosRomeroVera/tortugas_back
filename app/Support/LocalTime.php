<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/** Conversión entre la hora local de Cozumel y UTC. */
final class LocalTime
{
    public static function zone(): string
    {
        return config('tortugas.display_timezone');
    }

    /** Fecha y hora local (p. ej. '2026-11-15', '14:30') a instante UTC. */
    public static function toUtc(string $date, string $time): CarbonImmutable
    {
        return CarbonImmutable::parse("{$date} {$time}", self::zone())->utc();
    }

    /** Inicio y fin (UTC) del día local indicado. */
    public static function dayBounds(string $date): array
    {
        $start = CarbonImmutable::parse($date, self::zone())->startOfDay();

        return [$start->utc(), $start->addDay()->utc()];
    }

    public static function today(): string
    {
        return CarbonImmutable::now(self::zone())->toDateString();
    }
}
