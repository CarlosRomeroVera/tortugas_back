<?php

namespace App\Console\Commands;

use App\Actions\Departures\ScheduleDeparture;
use App\Exceptions\DomainException;
use App\Models\Departure;
use App\Models\ServiceSchedule;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/** Crea las salidas compartidas de los próximos días a partir de los horarios fijos. */
class GenerateDepartures extends Command
{
    protected $signature = 'departures:generate {--days=14 : Días hacia adelante} {--from= : Fecha local inicial (AAAA-MM-DD)}';

    protected $description = 'Genera salidas compartidas según los horarios fijos de cada servicio';

    public function handle(ScheduleDeparture $schedule): int
    {
        $from = CarbonImmutable::parse($this->option('from') ?? LocalTime::today(), LocalTime::zone());
        $created = 0;
        $skipped = 0;

        $schedules = ServiceSchedule::query()
            ->with(['service', 'asset'])
            ->whereNotNull('asset_id')
            ->whereHas('service', fn ($q) => $q->where('is_active', true)->where('is_shared', true))
            ->get();

        for ($i = 0; $i < (int) $this->option('days'); $i++) {
            $day = $from->addDays($i);

            foreach ($schedules as $row) {
                if ($row->weekday !== $day->dayOfWeek
                    || ($row->valid_from && $day->lt($row->valid_from))
                    || ($row->valid_until && $day->gt($row->valid_until))) {
                    continue;
                }

                $startsAt = LocalTime::toUtc($day->toDateString(), $row->departs_at);

                $exists = Departure::query()
                    ->where('service_id', $row->service_id)
                    ->where('asset_id', $row->asset_id)
                    ->where('starts_at', $startsAt)
                    ->exists();

                if ($exists) {
                    continue;
                }

                try {
                    $schedule->handle($row->service, $row->asset, $startsAt);
                    $created++;
                } catch (DomainException $e) {
                    $skipped++;
                    $this->warn("{$day->toDateString()} {$row->departs_at} {$row->service->name}: {$e->getMessage()}");
                }
            }
        }

        $this->info("Salidas creadas: {$created}. Omitidas por choque de agenda: {$skipped}.");

        return self::SUCCESS;
    }
}
