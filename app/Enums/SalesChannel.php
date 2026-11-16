<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SalesChannel: string implements HasColor, HasLabel
{
    case Website = 'website';
    case WhatsApp = 'whatsapp';
    case Agency = 'agency';
    case Counter = 'counter';
    case Phone = 'phone';

    public function getLabel(): string
    {
        return match ($this) {
            self::Website => 'Sitio web',
            self::WhatsApp => 'WhatsApp',
            self::Agency => 'Agencia',
            self::Counter => 'Mostrador',
            self::Phone => 'Teléfono',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Website => 'info',
            self::WhatsApp => 'success',
            self::Agency => 'warning',
            self::Counter => 'gray',
            self::Phone => 'gray',
        };
    }
}
