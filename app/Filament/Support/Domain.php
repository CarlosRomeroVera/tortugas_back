<?php

namespace App\Filament\Support;

use App\Exceptions\DomainException;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;

/**
 * Ejecuta una Action de negocio desde Filament. Si una regla la rechaza
 * (recurso ocupado, sin lugar…) muestra el motivo y detiene el formulario
 * sin cerrar el modal.
 */
final class Domain
{
    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function run(callable $callback, ?string $success = null): mixed
    {
        try {
            $result = $callback();
        } catch (DomainException|\InvalidArgumentException $e) {
            Notification::make()->danger()->title('No se pudo completar')->body($e->getMessage())->persistent()->send();

            throw (new Halt)->rollBackDatabaseTransaction();
        }

        if ($success) {
            Notification::make()->success()->title($success)->send();
        }

        return $result;
    }

    /** Pesos capturados en un formulario → centavos. */
    public static function toCents(mixed $pesos): ?int
    {
        return blank($pesos) ? null : (int) round(((float) $pesos) * 100);
    }

    public static function toPesos(?int $cents): ?string
    {
        return $cents === null ? null : number_format($cents / 100, 2, '.', '');
    }
}
