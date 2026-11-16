<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DepartureStatus: string implements HasColor, HasLabel
{
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Scheduled => 'Programada',
            self::InProgress => 'En curso',
            self::Completed => 'Finalizada',
            self::Cancelled => 'Cancelada',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Scheduled => 'info',
            self::InProgress => 'primary',
            self::Completed => 'gray',
            self::Cancelled => 'danger',
        };
    }
}
