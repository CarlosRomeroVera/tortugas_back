<?php

namespace App\Support;

use Illuminate\Support\Str;

/** Códigos legibles para personas: R-7KQ2MX, SAL-20261115-A3F9, OT-9X2KQ4. */
final class Codes
{
    private const ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ'; // sin 0/O ni 1/I

    public static function make(string $prefix, int $length = 6, ?string $middle = null): string
    {
        $random = '';
        for ($i = 0; $i < $length; $i++) {
            $random .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return Str::of($prefix)->append('-', $middle ? $middle.'-' : '', $random)->toString();
    }
}
