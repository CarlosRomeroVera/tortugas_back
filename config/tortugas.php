<?php

return [
    // Las fechas se guardan en UTC; así se muestran al usuario.
    'display_timezone' => env('APP_DISPLAY_TIMEZONE', 'America/Cancun'),

    // Horario de operación (hora local) para calcular huecos de servicios privados.
    'operating_hours' => [
        'start' => env('OPS_DAY_START', '08:00'),
        'end' => env('OPS_DAY_END', '18:00'),
    ],

    // Cada cuántos minutos se ofrece un horario de inicio en servicios privados.
    'slot_step_minutes' => (int) env('OPS_SLOT_STEP', 30),

    // Pesos por 1 USD si no se indica otro al registrar un pago.
    'default_usd_rate' => (float) env('OPS_DEFAULT_USD_RATE', 18.00),
];
