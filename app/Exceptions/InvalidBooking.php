<?php

namespace App\Exceptions;

class InvalidBooking extends DomainException
{
    public function status(): int
    {
        return 422;
    }
}
