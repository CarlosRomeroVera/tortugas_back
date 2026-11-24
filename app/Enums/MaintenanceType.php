<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum MaintenanceType: string implements HasColor, HasLabel
{
    case Preventive = 'preventive';
    case Corrective = 'corrective';

    public function getLabel(): string
    {
        return match ($this) {
            self::Preventive => 'Preventivo',
            self::Corrective => 'Correctivo',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Preventive => 'info',
            self::Corrective => 'danger',
        };
    }
}
