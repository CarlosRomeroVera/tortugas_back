<?php

namespace App\Actions\Field;

use RuntimeException;

/**
 * El evento no se puede aplicar y reenviarlo igual no cambiará el resultado.
 * No sale de RecordFieldEvent: se convierte en un resultado "rejected".
 */
final class FieldEventRejected extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}
