<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AllocationKind: string implements HasColor, HasLabel
{
    case Departure = 'departure';
    case Maintenance = 'maintenance';
    case Block = 'block';

    public function getLabel(): string
    {
        return match ($this) {
            self::Departure => 'Salida',
            self::Maintenance => 'Mantenimiento',
            self::Block => 'Bloqueo manual',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Departure => 'info',
            self::Maintenance => 'warning',
            self::Block => 'danger',
        };
    }
}
