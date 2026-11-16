<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum FaultSeverity: string implements HasColor, HasLabel
{
    case Minor = 'minor';       // puede seguir operando
    case Blocking = 'blocking'; // no debe salir hasta revisarse

    public function getLabel(): string
    {
        return match ($this) {
            self::Minor => 'Puede operar',
            self::Blocking => 'No puede operar',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Minor => 'warning',
            self::Blocking => 'danger',
        };
    }
}
