<?php

use Illuminate\Support\Facades\Schedule;

// Mantiene creadas las salidas compartidas de las próximas dos semanas.
Schedule::command('departures:generate --days=14')->dailyAt('03:00')->timezone('America/Cancun');
