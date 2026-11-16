<?php

namespace App\Actions\Crew;

use App\Enums\DepartureStatus;
use App\Enums\StaffRole;
use App\Exceptions\CrewUnavailable;
use App\Exceptions\InvalidBooking;
use App\Models\Departure;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;

/**
 * Asigna una persona a la tripulación de una salida. Una misma persona no puede
 * ir en dos salidas que se traslapen. Se bloquea la fila del personal para que
 * dos coordinadores no lo asignen a la vez en horarios que chocan.
 */
final class AssignCrew
{
    public function handle(Departure $departure, Staff $staff, ?StaffRole $role = null): void
    {
        if (in_array($departure->status, [DepartureStatus::Cancelled, DepartureStatus::Completed], true)) {
            throw new InvalidBooking("La salida {$departure->code} ya no admite cambios de tripulación.");
        }

        DB::transaction(function () use ($departure, $staff, $role) {
            /** @var Staff $locked */
            $locked = Staff::query()->whereKey($staff->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->is_active) {
                throw new CrewUnavailable("{$locked->name} está dado de baja.");
            }

            $clash = $locked->departures()
                ->whereKeyNot($departure->getKey())
                ->where('status', '!=', DepartureStatus::Cancelled)
                ->where('starts_at', '<', $departure->ends_at)
                ->where('ends_at', '>', $departure->starts_at)
                ->first();

            if ($clash) {
                $tz = config('tortugas.display_timezone');

                throw new CrewUnavailable(sprintf(
                    '%s ya va en %s de %s a %s.',
                    $locked->name,
                    $clash->code,
                    $clash->starts_at->tz($tz)->format('H:i'),
                    $clash->ends_at->tz($tz)->format('H:i'),
                ));
            }

            $departure->crew()->syncWithoutDetaching([
                $locked->getKey() => ['role' => ($role ?? $locked->role)->value],
            ]);
        });
    }
}
