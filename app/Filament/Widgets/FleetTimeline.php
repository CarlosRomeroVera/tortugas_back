<?php

namespace App\Filament\Widgets;

use App\Enums\AllocationKind;
use App\Enums\AssetKind;
use App\Filament\Resources\Departures\DepartureResource;
use App\Filament\Resources\MaintenanceOrders\MaintenanceOrderResource;
use App\Models\Allocation;
use App\Models\Asset;
use App\Models\Departure;
use App\Models\MaintenanceOrder;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;
use Filament\Widgets\Widget;

/**
 * Agenda por horas de cada embarcación o vehículo (sale de la tabla allocations).
 * En la fase 2 se refresca con Reverb; por ahora consulta cada 30 s.
 */
class FleetTimeline extends Widget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.fleet-timeline';

    public string $date = '';

    public string $kind = 'boat';

    public function mount(): void
    {
        $this->date = LocalTime::today();
    }

    public function shiftDay(int $days): void
    {
        $this->date = CarbonImmutable::parse($this->date)->addDays($days)->toDateString();
    }

    public function today(): void
    {
        $this->date = LocalTime::today();
    }

    protected function getViewData(): array
    {
        $tz = LocalTime::zone();
        $hours = config('tortugas.operating_hours');
        $start = CarbonImmutable::parse("{$this->date} {$hours['start']}", $tz)->subHour();
        $end = CarbonImmutable::parse("{$this->date} {$hours['end']}", $tz)->addHour();
        $span = $start->diffInMinutes($end);

        $assets = Asset::query()
            ->where('kind', AssetKind::from($this->kind))
            ->orderBy('name')
            ->get();

        $allocations = Allocation::query()
            ->active()
            ->whereIn('asset_id', $assets->modelKeys())
            ->overlapping($start->utc(), $end->utc())
            ->with(['allocatable' => fn ($morph) => $morph->morphWith([
                Departure::class => ['service'],
            ])])
            ->get()
            ->groupBy('asset_id');

        $now = CarbonImmutable::now($tz);
        $pct = fn (CarbonImmutable $t) => max(0, min(100, $start->diffInMinutes($t, false) / $span * 100));

        $rows = $assets->map(fn (Asset $asset) => [
            'asset' => $asset,
            'blocks' => ($allocations[$asset->id] ?? collect())->map(function (Allocation $a) use ($tz, $pct, $now) {
                $from = CarbonImmutable::instance($a->starts_at)->tz($tz);
                $to = CarbonImmutable::instance($a->ends_at)->tz($tz);
                $record = $a->allocatable;

                [$title, $subtitle, $tone, $url] = match (true) {
                    $record instanceof Departure => [
                        $record->service->name.' '.$from->format('H:i'),
                        $record->seatsTaken().'/'.$record->capacity.' · '.$record->status->getLabel(),
                        $to <= $now ? 'done' : ($from <= $now ? 'live' : 'booked'),
                        DepartureResource::getUrl('view', ['record' => $record]),
                    ],
                    $record instanceof MaintenanceOrder => [
                        'Mantenimiento', $record->code.' · '.$record->title, 'maintenance',
                        MaintenanceOrderResource::getUrl('view', ['record' => $record]),
                    ],
                    default => ['Bloqueo', $a->reason, 'block', null],
                };

                return [
                    'left' => $pct($from),
                    'width' => max(1.5, $pct($to) - $pct($from)),
                    'title' => $title,
                    'subtitle' => $subtitle,
                    'tone' => $a->kind === AllocationKind::Departure ? $tone : ($a->kind === AllocationKind::Maintenance ? 'maintenance' : 'block'),
                    'url' => $url,
                    'range' => $from->format('H:i').'–'.$to->format('H:i'),
                ];
            })->values(),
        ]);

        $ticks = [];
        for ($t = $start; $t <= $end; $t = $t->addHour()) {
            $ticks[] = ['label' => $t->format('H'), 'left' => $pct($t)];
        }

        return [
            'rows' => $rows,
            'ticks' => $ticks,
            'nowLeft' => $now->toDateString() === $this->date ? $pct($now) : null,
            'nowLabel' => $now->format('H:i'),
            'dateLabel' => CarbonImmutable::parse($this->date, $tz)->locale('es')->translatedFormat('l d \d\e F'),
            'isToday' => $this->date === LocalTime::today(),
        ];
    }
}
