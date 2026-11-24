<?php

namespace App\Exceptions;

class NoSeatsLeft extends DomainException
{
    public static function for(int $requested, int $left): self
    {
        return new self("No hay lugar para {$requested} personas: quedan {$left}.");
    }
}
