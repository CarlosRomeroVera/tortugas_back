<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AllocationStatus: string implements HasColor, HasLabel
{
    case Active = 'active';
    case Released = 'released';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => 'Activa',
            self::Released => 'Liberada',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Released => 'gray',
        };
    }
}
