<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StaffRole: string implements HasColor, HasLabel
{
    case Captain = 'captain';
    case Driver = 'driver';
    case Guide = 'guide';
    case Mechanic = 'mechanic';

    public function getLabel(): string
    {
        return match ($this) {
            self::Captain => 'Capitán',
            self::Driver => 'Chofer',
            self::Guide => 'Guía',
            self::Mechanic => 'Mecánico',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Captain => 'primary',
            self::Driver => 'info',
            self::Guide => 'success',
            self::Mechanic => 'warning',
        };
    }
}
