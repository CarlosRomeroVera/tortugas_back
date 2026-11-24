<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ServiceCategory: string implements HasColor, HasLabel
{
    case Snorkel = 'snorkel';
    case PrivateBoat = 'private_boat';
    case Parasailing = 'parasailing';
    case JeepTour = 'jeep_tour';
    case BeachClub = 'beach_club';
    case Atv = 'atv';
    case JetSki = 'jet_ski';

    public function getLabel(): string
    {
        return match ($this) {
            self::Snorkel => 'Snorkel',
            self::PrivateBoat => 'Embarcación privada',
            self::Parasailing => 'Parasailing',
            self::JeepTour => 'Jeep tour',
            self::BeachClub => 'Club de playa',
            self::Atv => 'ATV',
            self::JetSki => 'Moto acuática',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Snorkel => 'info',
            self::PrivateBoat => 'primary',
            self::Parasailing => 'warning',
            self::JeepTour => 'success',
            self::BeachClub => 'gray',
            self::Atv => 'danger',
            self::JetSki => 'info',
        };
    }
}
