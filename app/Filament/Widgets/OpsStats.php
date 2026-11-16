<?php

namespace App\Filament\Widgets;

use App\Enums\AssetKind;
use App\Enums\AssetStatus;
use App\Enums\BookingStatus;
use App\Enums\DepartureStatus;
use App\Enums\PaymentStatus;
use App\Models\Allocation;
use App\Models\Asset;
use App\Models\Booking;
use App\Models\Departure;
use App\Models\Payment;
use App\Support\LocalTime;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Indicadores del día (hora de Cozumel). */
class OpsStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $now = now();
        [$from, $to] = LocalTime::dayBounds(LocalTime::today());

        $boats = Asset::query()->where('kind', AssetKind::Boat)->where('status', AssetStatus::Active)->pluck('id');
        $busyNow = Allocation::query()->active()->whereIn('asset_id', $boats)
            ->where('starts_at', '<=', $now)->where('ends_at', '>', $now)->distinct()->count('asset_id');
        $maintenance = Asset::query()->where('kind', AssetKind::Boat)->where('status', '!=', AssetStatus::Active)->count()
            + Allocation::query()->active()->where('kind', 'maintenance')->whereIn('asset_id', $boats)
                ->where('starts_at', '<=', $now)->where('ends_at', '>', $now)->count();

        $today = Departure::query()->where('starts_at', '>=', $from)->where('starts_at', '<', $to);
        $inProgress = (clone $today)->where('status', DepartureStatus::InProgress)->count();
        $scheduled = (clone $today)->where('status', DepartureStatus::Scheduled)->count();

        $paxToday = Booking::query()->occupyingSeats()
            ->whereHas('departure', fn ($query) => $query->where('starts_at', '>=', $from)->where('starts_at', '<', $to))
            ->sum('pax');

        $collected = Payment::query()->where('status', PaymentStatus::Paid)
            ->where('paid_at', '>=', $from)->where('paid_at', '<', $to)->sum('amount_mxn_cents');

        $pending = Booking::query()->where('status', BookingStatus::Pending)
            ->whereHas('departure', fn ($query) => $query->where('starts_at', '>=', $from)->where('starts_at', '<', $to))->count();

        return [
            Stat::make('Embarcaciones libres ahora', ($boats->count() - $busyNow).' / '.$boats->count())
                ->description($maintenance ? "{$maintenance} en mantenimiento" : 'Ninguna en mantenimiento')
                ->color('primary'),
            Stat::make('Salidas de hoy', $inProgress + $scheduled)
                ->description("{$inProgress} en curso · {$scheduled} por salir"),
            Stat::make('Personas hoy', (int) $paxToday)
                ->description($pending ? "{$pending} reservas sin pago" : 'Todas con pago')
                ->descriptionColor($pending ? 'warning' : 'success'),
            Stat::make('Cobrado hoy', '$'.number_format($collected / 100, 0).' MXN')
                ->description('Pagos registrados hoy, convertidos a pesos'),
        ];
    }
}
