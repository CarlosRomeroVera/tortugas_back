<?php

namespace App\Providers;

use App\Models\Allocation;
use App\Models\Asset;
use App\Models\Booking;
use App\Models\Departure;
use App\Models\FaultReport;
use App\Models\MaintenanceOrder;
use App\Models\Payment;
use App\Models\User;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Nombres cortos y estables en columnas polimórficas (allocatable, activity_log, roles).
        Relation::enforceMorphMap([
            'user' => User::class,
            'asset' => Asset::class,
            'allocation' => Allocation::class,
            'departure' => Departure::class,
            'maintenance_order' => MaintenanceOrder::class,
            'booking' => Booking::class,
            'payment' => Payment::class,
            'fault_report' => FaultReport::class,
        ]);

        // En desarrollo, falla ante atributos inexistentes o descartados en silencio.
        Model::preventAccessingMissingAttributes(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        // Se guarda en UTC; Filament muestra y captura en hora de Cozumel.
        FilamentTimezone::set(config('tortugas.display_timezone'));
    }
}
