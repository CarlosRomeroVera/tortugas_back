<?php

namespace App\Filament\Widgets;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\Booking;
use App\Models\Staff;
use App\Support\LocalTime;
use Filament\Widgets\Widget;

/** Lo que coordinación debe atender: cruceros en riesgo, licencias y servicios preventivos. */
class OpsAlerts extends Widget
{
    /** Minutos mínimos entre el regreso del tour y el all aboard. */
    public const CRUISE_MARGIN_MINUTES = 60;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.ops-alerts';

    protected function getViewData(): array
    {
        $tz = LocalTime::zone();
        $alerts = collect();

        Booking::query()->occupyingSeats()->whereNotNull('all_aboard_at')
            ->whereHas('departure', fn ($query) => $query->where('ends_at', '>', now())->where('starts_at', '<', now()->addDays(2)))
            ->with(['departure.service', 'customer'])
            ->get()
            ->filter(fn (Booking $b) => $b->departure->ends_at->diffInMinutes($b->all_aboard_at, false) < self::CRUISE_MARGIN_MINUTES)
            ->each(fn (Booking $b) => $alerts->push([
                'level' => 'danger',
                'title' => "Crucero {$b->cruise_ship}: all aboard {$b->all_aboard_at->tz($tz)->format('d/m H:i')}",
                'body' => "{$b->customer->name} ({$b->pax} pax) regresa de {$b->departure->service->name} a las {$b->departure->ends_at->tz($tz)->format('H:i')}. Margen: "
                    .$b->departure->ends_at->diffInMinutes($b->all_aboard_at, false).' min.',
            ]));

        Asset::query()->where('status', AssetStatus::Active)->whereNotNull('service_interval')->get()
            ->filter(fn (Asset $a) => $a->currentReading() !== null && $a->currentReading() >= $a->nextServiceReading() - 0.1 * $a->service_interval)
            ->each(fn (Asset $a) => $alerts->push([
                'level' => $a->currentReading() >= $a->nextServiceReading() ? 'danger' : 'warning',
                'title' => "{$a->name}: servicio preventivo",
                'body' => 'Lectura '.number_format($a->currentReading()).', servicio a las '.number_format($a->nextServiceReading()).'.',
            ]));

        Staff::query()->where('is_active', true)->whereNotNull('license_expires_on')
            ->where('license_expires_on', '<', now()->addDays(30))->get()
            ->each(fn (Staff $s) => $alerts->push([
                'level' => $s->license_expires_on->isPast() ? 'danger' : 'warning',
                'title' => "Licencia de {$s->name}",
                'body' => ($s->license_expires_on->isPast() ? 'Venció el ' : 'Vence el ').$s->license_expires_on->format('d/m/Y').'.',
            ]));

        return ['alerts' => $alerts->sortBy(fn ($a) => $a['level'] === 'danger' ? 0 : 1)->values()];
    }
}
