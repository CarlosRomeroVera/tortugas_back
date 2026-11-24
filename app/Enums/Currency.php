<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum Currency: string implements HasColor, HasLabel
{
    case MXN = 'MXN';
    case USD = 'USD';

    public function getLabel(): string
    {
        return match ($this) {
            self::MXN => 'Pesos (MXN)',
            self::USD => 'Dólares (USD)',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::MXN => 'success',
            self::USD => 'info',
        };
    }
}
