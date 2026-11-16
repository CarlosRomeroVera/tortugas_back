<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Error de regla de negocio. La API responde 409 con el mensaje;
 * Filament lo muestra como notificación.
 */
abstract class DomainException extends RuntimeException
{
    public function status(): int
    {
        return 409;
    }
}
