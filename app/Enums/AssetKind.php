<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AssetKind: string implements HasColor, HasLabel
{
    case Boat = 'boat';
    case Vehicle = 'vehicle';

    public function getLabel(): string
    {
        return match ($this) {
            self::Boat => 'Embarcación',
            self::Vehicle => 'Vehículo',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Boat => 'info',
            self::Vehicle => 'gray',
        };
    }
}
