<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum LocationType: string implements HasColor, HasLabel
{
    case Hotel = 'hotel';
    case Pier = 'pier';
    case CruiseTerminal = 'cruise_terminal';
    case Ferry = 'ferry';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Hotel => 'Hotel',
            self::Pier => 'Muelle',
            self::CruiseTerminal => 'Terminal de cruceros',
            self::Ferry => 'Muelle de ferry',
            self::Other => 'Otro',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Hotel => 'info',
            self::Pier => 'primary',
            self::CruiseTerminal => 'warning',
            self::Ferry => 'success',
            self::Other => 'gray',
        };
    }
}
