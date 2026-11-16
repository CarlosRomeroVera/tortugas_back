<?php

namespace App\Filament\Resources\Departures\Pages;

use App\Actions\Departures\ScheduleDeparture;
use App\Filament\Resources\Departures\DepartureResource;
use App\Filament\Support\Domain;
use App\Models\Asset;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateDeparture extends CreateRecord
{
    protected static string $resource = DepartureResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return Domain::run(fn () => app(ScheduleDeparture::class)->handle(
            Service::findOrFail($data['service_id']),
            Asset::findOrFail($data['asset_id']),
            CarbonImmutable::parse($data['starts_at'])->utc(),
            filled($data['capacity'] ?? null) ? (int) $data['capacity'] : null,
        ));
    }
}
