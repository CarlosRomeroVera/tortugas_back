<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AssetStatus: string implements HasColor, HasLabel
{
    case Active = 'active';
    case OutOfService = 'out_of_service';
    case Retired = 'retired';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => 'Activo',
            self::OutOfService => 'Fuera de servicio',
            self::Retired => 'Dado de baja',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::OutOfService => 'danger',
            self::Retired => 'gray',
        };
    }
}
