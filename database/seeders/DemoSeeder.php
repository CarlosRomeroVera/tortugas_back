<?php

namespace Database\Seeders;

use App\Actions\Bookings\BookingData;
use App\Actions\Bookings\CreateBooking;
use App\Actions\Crew\AssignCrew;
use App\Actions\Maintenance\ScheduleMaintenance;
use App\Actions\Payments\RecordPayment;
use App\Enums\AssetKind;
use App\Enums\Currency;
use App\Enums\LocationType;
use App\Enums\MaintenanceType;
use App\Enums\PaymentMethod;
use App\Enums\SalesChannel;
use App\Enums\ServiceCategory;
use App\Enums\StaffRole;
use App\Exceptions\DomainException;
use App\Models\Asset;
use App\Models\Departure;
use App\Models\Location;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use App\Support\LocalTime;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

/**
 * Datos de ejemplo para desarrollo. Nombres, horarios y precios son inventados;
 * reemplázalos por los reales del cliente.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['Administrador', 'admin@tortugas.test', 'admin'],
            ['Coordinación', 'coordinacion@tortugas.test', 'coordinacion'],
            ['Ventas', 'ventas@tortugas.test', 'ventas'],
            ['Rubén Canul', 'capitan@tortugas.test', 'capitan'],
            ['Pedro Uc', 'chofer@tortugas.test', 'chofer'],
        ];
        foreach ($users as [$name, $email, $role]) {
            User::query()->firstOrCreate(['email' => $email], ['name' => $name, 'password' => 'password'])
                ->syncRoles([$role]);
        }

        $boats = [
            ['Carey I', '2301-QR', 24, 1812, 2000, 0],
            ['Carey II', '2302-QR', 24, 1966, 2000, 0],
            ['Tortuga', '2305-QR', 30, 3020, 3500, 3000],
            ['El Cielo', '2310-QR', 12, 940, 1000, 0],
            ['Caguama', '2311-QR', 10, 2140, 1000, 1000],
            ['Laúd', '2315-QR', 8, 410, 500, 0],
            ['Parasail 1', '2320-QR', 10, 1205, 1500, 0],
            ['Parasail 2', '2321-QR', 10, 300, 1500, 0],
        ];
        foreach ($boats as [$name, $reg, $cap, $hours, $interval, $last]) {
            Asset::query()->firstOrCreate(['kind' => AssetKind::Boat, 'name' => $name], [
                'registration' => $reg, 'capacity' => $cap, 'engine_hours' => $hours,
                'service_interval' => $interval, 'last_service_reading' => $last,
            ]);
        }

        $vehicles = [
            ['Van 1', 'YXW-21-44', 'Toyota Hiace', 12, 148320],
            ['Van 2', 'YXW-21-45', 'Toyota Hiace', 12, 96510],
            ['Sprinter', 'YXW-30-12', 'Mercedes Sprinter', 19, 61900],
            ['Jeep 2', 'YXW-18-02', 'Jeep Wrangler', 6, 88045],
            ['Jeep 3', 'YXW-18-03', 'Jeep Wrangler', 6, 90110],
        ];
        foreach ($vehicles as [$name, $plate, $model, $cap, $km]) {
            Asset::query()->firstOrCreate(['kind' => AssetKind::Vehicle, 'name' => $name], [
                'registration' => $plate, 'model' => $model, 'capacity' => $cap,
                'odometer_km' => $km, 'service_interval' => 10000, 'last_service_reading' => floor($km / 10000) * 10000,
            ]);
        }

        $locations = [
            ['Terminal Puerta Maya', LocationType::CruiseTerminal, 20.469700, -86.977500],
            ['Muelle Punta Langosta', LocationType::CruiseTerminal, 20.505600, -86.956500],
            ['Muelle de ferry', LocationType::Ferry, 20.510700, -86.951300],
            ['Muelle Tortugas', LocationType::Pier, 20.474000, -86.973000],
            ['Hotel Presidente', LocationType::Hotel, 20.467500, -86.980000],
            ['Cozumel Palace', LocationType::Hotel, 20.501000, -86.958500],
        ];
        foreach ($locations as [$name, $type, $lat, $lng]) {
            Location::query()->firstOrCreate(['name' => $name], ['type' => $type, 'latitude' => $lat, 'longitude' => $lng]);
        }

        $staff = [
            ['Rubén Canul', StaffRole::Captain, 'capitan@tortugas.test'],
            ['Jorge May', StaffRole::Captain, null],
            ['Luis Pech', StaffRole::Captain, null],
            ['Ana Chi', StaffRole::Captain, null],
            ['Pedro Uc', StaffRole::Driver, 'chofer@tortugas.test'],
            ['Iván Dzul', StaffRole::Driver, null],
            ['Karla Ek', StaffRole::Guide, null],
            ['Marco Tun', StaffRole::Guide, null],
        ];
        foreach ($staff as [$name, $role, $email]) {
            Staff::query()->firstOrCreate(['name' => $name], [
                'role' => $role,
                'user_id' => $email ? User::query()->where('email', $email)->value('id') : null,
                'license_expires_on' => now()->addMonths(rand(1, 18)),
            ]);
        }

        $snorkel = $this->service('snorkel-3-arrecifes', [
            'name' => 'Snorkel 3 arrecifes', 'category' => ServiceCategory::Snorkel, 'asset_kind' => AssetKind::Boat,
            'is_shared' => true, 'duration_minutes' => 150, 'buffer_minutes' => 30, 'default_capacity' => 24,
            'price_adult_cents' => 138000, 'price_child_cents' => 99000,
        ], ['Check-in en muelle', 'Zarpe', 'Arrecife Palancar', 'Arrecife Colombia', 'Regreso a muelle', 'Desembarque y cierre']);

        $this->service('embarcacion-privada', [
            'name' => 'Embarcación privada', 'category' => ServiceCategory::PrivateBoat, 'asset_kind' => AssetKind::Boat,
            'is_shared' => false, 'duration_minutes' => 240, 'buffer_minutes' => 30,
            'price_adult_cents' => 174000, 'price_child_cents' => 174000,
        ], ['Check-in en muelle', 'Zarpe', 'Arrecife', 'El Cielo', 'Regreso a muelle', 'Desembarque y cierre']);

        $parasail = $this->service('parasailing', [
            'name' => 'Parasailing', 'category' => ServiceCategory::Parasailing, 'asset_kind' => AssetKind::Boat,
            'is_shared' => true, 'duration_minutes' => 50, 'buffer_minutes' => 10, 'default_capacity' => 10,
            'price_adult_cents' => 157900, 'price_child_cents' => 157900,
        ], ['Check-in', 'En el aire', 'Regreso', 'Cierre']);

        $jeep = $this->service('jeep-tour', [
            'name' => 'Jeep tour', 'category' => ServiceCategory::JeepTour, 'asset_kind' => AssetKind::Vehicle,
            'is_shared' => true, 'duration_minutes' => 300, 'buffer_minutes' => 30, 'default_capacity' => 6,
            'price_adult_cents' => 149100, 'price_child_cents' => 120000,
        ], ['Recogida', 'Costa oriente', 'Comida', 'Punta Sur', 'Regreso']);

        $schedule = [
            [$snorkel, 'Carey I', ['08:30', '11:30', '15:00']],
            [$snorkel, 'Carey II', ['10:00', '13:30']],
            [$snorkel, 'Tortuga', ['14:30']],
            [$parasail, 'Parasail 1', ['09:00', '10:00', '11:00', '13:00', '14:00']],
            [$parasail, 'Parasail 2', ['14:00', '15:00']],
            [$jeep, 'Jeep 2', ['09:30']],
            [$jeep, 'Jeep 3', ['09:30']],
        ];
        foreach ($schedule as [$service, $assetName, $times]) {
            $asset = Asset::query()->where('name', $assetName)->firstOrFail();
            foreach (range(0, 6) as $weekday) {
                foreach ($times as $time) {
                    $service->schedules()->firstOrCreate([
                        'asset_id' => $asset->id, 'weekday' => $weekday, 'departs_at' => $time.':00',
                    ]);
                }
            }
        }

        Artisan::call('departures:generate', ['--days' => 7, '--from' => now(LocalTime::zone())->addDay()->toDateString()]);

        $this->bookings();
        $this->crew();
        $this->maintenance();
    }

    /** Tripulación fija por unidad. Rubén (capitán) y Pedro (chofer) tienen usuario para la app. */
    private function crew(): void
    {
        $byAsset = [
            'Carey I' => ['Rubén Canul', 'Karla Ek'],
            'Carey II' => ['Jorge May'],
            'Tortuga' => ['Luis Pech'],
            'Laúd' => ['Luis Pech'],
            'El Cielo' => ['Luis Pech'],
            'Parasail 1' => ['Ana Chi'],
            'Parasail 2' => ['Ana Chi'],
            'Jeep 2' => ['Pedro Uc', 'Marco Tun'],
            'Jeep 3' => ['Iván Dzul'],
        ];
        $assign = app(AssignCrew::class);
        $staff = Staff::query()->get()->keyBy('name');

        Departure::query()->with('asset')->get()->each(function (Departure $d) use ($byAsset, $assign, $staff) {
            foreach ($byAsset[$d->asset->name] ?? [] as $name) {
                try {
                    $assign->handle($d, $staff[$name]);
                } catch (DomainException) {
                    // Ya va en otra salida a esa hora: queda sin asignar para que coordinación lo vea.
                }
            }
        });
    }

    private function service(string $slug, array $attrs, array $stages): Service
    {
        $service = Service::query()->firstOrCreate(['slug' => $slug], $attrs + ['currency' => Currency::MXN]);
        foreach ($stages as $i => $name) {
            $service->stages()->firstOrCreate(['position' => $i + 1], [
                'name' => $name, 'requires_reading' => $i === count($stages) - 1,
            ]);
        }

        return $service;
    }

    private function bookings(): void
    {
        $create = app(CreateBooking::class);
        $pay = app(RecordPayment::class);
        $names = ['Emily Carter', 'Familia Gómez', 'Daniel Ruiz', 'Sarah Miller', 'Grupo Cozumel Travel', 'Luis Hernández', 'Jennifer Lee'];
        $channels = SalesChannel::cases();

        Departure::query()->with('service')->orderBy('starts_at')->limit(25)->get()
            ->each(function (Departure $d, int $i) use ($create, $pay, $names, $channels) {
                $adults = min(rand(1, 4), $d->capacity);
                $booking = $create->handle(new BookingData(
                    customer: ['name' => $names[$i % count($names)], 'email' => 'cliente'.$i.'@example.com'],
                    adults: $adults,
                    children: rand(0, 1) && $adults < $d->capacity ? 1 : 0,
                    channel: $channels[$i % count($channels)],
                    departureId: $d->id,
                    cruiseShip: $i % 3 === 0 ? 'Icon of the Seas' : null,
                ));
                if ($i % 4 !== 3) {
                    $pay->handle($booking, $booking->total_cents, Currency::MXN, PaymentMethod::Card);
                }
            });

        // Un privado de ejemplo pasado mañana a las 10:00.
        $create->handle(new BookingData(
            customer: ['name' => 'Familia Harris', 'email' => 'harris@example.com', 'country' => 'US'],
            adults: 6,
            channel: SalesChannel::Website,
            serviceId: Service::query()->where('slug', 'embarcacion-privada')->value('id'),
            startsAt: LocalTime::toUtc(now(LocalTime::zone())->addDays(2)->toDateString(), '10:00'),
            cruiseShip: 'Icon of the Seas',
            allAboardAt: LocalTime::toUtc(now(LocalTime::zone())->addDays(2)->toDateString(), '17:30'),
        ));
    }

    private function maintenance(): void
    {
        $date = now(LocalTime::zone())->addDays(3)->toDateString();
        app(ScheduleMaintenance::class)->handle(
            Asset::query()->where('name', 'Caguama')->firstOrFail(),
            MaintenanceType::Corrective,
            'Bomba de achique',
            LocalTime::toUtc($date, '08:00'),
            LocalTime::toUtc($date, '14:00'),
        );
    }
}
