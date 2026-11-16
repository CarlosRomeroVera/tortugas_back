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
use Illuminate\Support\Str;

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

        // Flota de ejemplo: nombres, matrículas y lecturas inventados.
        $boats = [
            ['Carey I', '2301-QR', 'Catamarán', 24, 1812, 2000, 0],
            ['Carey II', '2302-QR', 'Catamarán', 24, 1966, 2000, 0],
            ['Tortuga', '2305-QR', 'Catamarán', 30, 3020, 3500, 3000],
            ['La Cosita', '2310-QR', 'Lancha privada', 12, 940, 1000, 0],
            ['Caguama', '2311-QR', 'Lancha privada', 10, 2140, 1000, 1000],
            ['Laúd', '2315-QR', 'Lancha privada', 8, 410, 500, 0],
            ['Parasail 1', '2320-QR', 'Lancha de parasailing', 10, 1205, 1500, 0],
            ['Parasail 2', '2321-QR', 'Lancha de parasailing', 10, 300, 1500, 0],
            ['Jet Ski 1', '2330-QR', 'Moto acuática', 2, 120, 100, 100],
            ['Jet Ski 2', '2331-QR', 'Moto acuática', 2, 85, 100, 0],
            ['Jet Ski 3', '2332-QR', 'Moto acuática', 2, 64, 100, 0],
        ];
        foreach ($boats as [$name, $reg, $model, $cap, $hours, $interval, $last]) {
            Asset::query()->firstOrCreate(['kind' => AssetKind::Boat, 'name' => $name], [
                'registration' => $reg, 'model' => $model, 'capacity' => $cap, 'engine_hours' => $hours,
                'service_interval' => $interval, 'last_service_reading' => $last,
            ]);
        }

        // Los grupos de ATV son una simplificación: hoy cada salida ocupa una sola unidad,
        // así que un convoy de cuatrimotos se registra como una unidad con cupo de grupo.
        $vehicles = [
            ['Van 1', 'YXW-21-44', 'Toyota Hiace', 12, 148320],
            ['Van 2', 'YXW-21-45', 'Toyota Hiace', 12, 96510],
            ['Sprinter', 'YXW-30-12', 'Mercedes Sprinter', 19, 61900],
            ['Jeep 1', 'YXW-18-01', 'Jeep Wrangler Sahara A/C', 6, 72300],
            ['Jeep 2', 'YXW-18-02', 'Jeep Wrangler Sahara A/C', 6, 88045],
            ['Jeep 3', 'YXW-18-03', 'Jeep Wrangler Sahara A/C', 6, 90110],
            ['Jeep 4', 'YXW-18-04', 'Jeep Wrangler Sahara A/C', 6, 101230],
            ['ATV Grupo A', null, 'Convoy de 8 cuatrimotos', 16, 15400],
            ['ATV Grupo B', null, 'Convoy de 8 cuatrimotos', 16, 12800],
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

        // Catálogo de tortugascozumel.com: nombre, itinerario, duración y precio en línea (USD).
        // Precio por persona: el sistema aún no maneja precio por embarcación.
        $catamaran = $this->service('Catamaran Snorkel to El Cielo', [
            'category' => ServiceCategory::Snorkel, 'asset_kind' => AssetKind::Boat, 'is_shared' => true,
            'duration_minutes' => 240, 'buffer_minutes' => 30, 'default_capacity' => 24, 'price_adult_cents' => 7599,
            'description' => 'El Cielo + Drinks & Snacks + Beach Break (4 Hours)',
        ], ['Check-in en muelle', 'Zarpe', 'El Cielo', 'Regreso a muelle', 'Beach break y cierre'],
            ['Carey I', 'Carey II', 'Tortuga']);

        $this->service('El Cielo by Private Boat', [
            'category' => ServiceCategory::PrivateBoat, 'asset_kind' => AssetKind::Boat, 'is_shared' => false,
            'duration_minutes' => 240, 'buffer_minutes' => 30, 'price_adult_cents' => 44999,
            'description' => 'Private Boat + Colombia & Palancar Reefs + El Cielo + Refreshments & Snacks + Beach Break (4 Hours)',
        ], ['Check-in en muelle', 'Zarpe', 'Arrecife Colombia', 'Arrecife Palancar', 'El Cielo', 'Regreso a muelle', 'Beach break y cierre'],
            ['La Cosita', 'Caguama', 'Laúd']);

        $parasail = $this->service('Parasailing at Tortugas Beach Club', [
            'category' => ServiceCategory::Parasailing, 'asset_kind' => AssetKind::Boat, 'is_shared' => true,
            'duration_minutes' => 60, 'buffer_minutes' => 10, 'default_capacity' => 10, 'price_adult_cents' => 8099,
            'description' => 'Parasailing + Beach Break (1 Hour). Approx 12 mins in the air',
        ], ['Check-in', 'Zarpe', 'En el aire', 'Regreso y beach break'],
            ['Parasail 1', 'Parasail 2']);

        $this->service('Jet Ski Rental & Tortugas Beach Club', [
            'category' => ServiceCategory::JetSki, 'asset_kind' => AssetKind::Boat, 'is_shared' => false,
            'duration_minutes' => 30, 'buffer_minutes' => 10, 'price_adult_cents' => 7199,
            'description' => 'Jet Ski Rental (30 Minutes) + Tortugas Beach Club',
        ], ['Check-in y chaleco', 'En el agua', 'Regreso y cierre'],
            ['Jet Ski 1', 'Jet Ski 2', 'Jet Ski 3']);

        $jeepAtv = $this->service('Jeep Tour & ATV Jungle Adventure to Jade Cavern', [
            'category' => ServiceCategory::JeepTour, 'asset_kind' => AssetKind::Vehicle, 'is_shared' => true,
            'duration_minutes' => 330, 'buffer_minutes' => 30, 'default_capacity' => 6, 'price_adult_cents' => 26999,
            'description' => 'Island Jeep Tour + Jungle ATV Tour + Jade Cavern + Mexican Lunch + Beach Break (5.5 Hours)',
        ], ['Recogida', 'Recorrido en Jeep', 'El Cedral: ATV en la selva', 'Jade Cavern y cata de tequila', 'Comida y snorkel de orilla', 'Cierre'],
            ['Jeep 2', 'Jeep 3']);

        $this->service('Cozumel by Jeep with Snorkel by Boat (Private)', [
            'category' => ServiceCategory::JeepTour, 'asset_kind' => AssetKind::Vehicle, 'is_shared' => false,
            'duration_minutes' => 300, 'buffer_minutes' => 30, 'price_adult_cents' => 9499,
            'description' => 'Jeep Island Tour + Snorkel by Boat + Mexican Lunch (5 Hours)',
        ], ['Recogida', 'Recorrido en Jeep', 'Snorkel en lancha', 'Comida mexicana', 'Cierre'],
            ['Jeep 1', 'Jeep 4']);

        $this->service('The Original Jeep Tour Revamped (Private)', [
            'category' => ServiceCategory::JeepTour, 'asset_kind' => AssetKind::Vehicle, 'is_shared' => false,
            'duration_minutes' => 330, 'buffer_minutes' => 30, 'price_adult_cents' => 7649,
            'description' => 'Island Tour + Theme Park + Guided Shore Snorkel + Lunch (5.5 Hours)',
        ], ['Recogida', 'Recorrido por la isla', 'Parque temático', 'Snorkel guiado de orilla', 'Comida', 'Cierre'],
            ['Jeep 1', 'Jeep 4']);

        $atvMud = $this->service('ATV Mud Kicking & Snorkel by Boat', [
            'category' => ServiceCategory::Atv, 'asset_kind' => AssetKind::Vehicle, 'is_shared' => true,
            'duration_minutes' => 330, 'buffer_minutes' => 30, 'default_capacity' => 16, 'price_adult_cents' => 12824,
            'description' => 'ATV Adventure + Jade Cavern + Snorkel by Boat + Lunch & Open Bar (5.5 Hours)',
        ], ['Check-in', 'Traslado a El Cedral', 'Recorrido en ATV', 'Jade Cavern', 'Snorkel en lancha', 'Comida y open bar', 'Cierre'],
            ['ATV Grupo A']);

        $atvTrails = $this->service('ATV Jungle Trails to Jade Cavern & Snorkel', [
            'category' => ServiceCategory::Atv, 'asset_kind' => AssetKind::Vehicle, 'is_shared' => true,
            'duration_minutes' => 210, 'buffer_minutes' => 30, 'default_capacity' => 16, 'price_adult_cents' => 9499,
            'description' => 'ATV Jungle Adventure + Jade Cavern + Shore Snorkel (3.5 Hours)',
        ], ['Check-in', 'Recorrido en ATV', 'Jade Cavern', 'Snorkel de orilla', 'Cierre'],
            ['ATV Grupo B']);

        // Horarios fijos de ejemplo (hora local). No se traslapan en la misma unidad, contando el margen.
        $schedule = [
            [$catamaran, 'Carey I', ['08:30', '13:00']],
            [$catamaran, 'Carey II', ['10:00']],
            [$catamaran, 'Tortuga', ['14:30']],
            [$parasail, 'Parasail 1', ['09:00', '10:30', '12:00', '13:30', '15:00']],
            [$parasail, 'Parasail 2', ['10:00', '14:00']],
            [$jeepAtv, 'Jeep 2', ['09:00']],
            [$jeepAtv, 'Jeep 3', ['09:00']],
            [$atvMud, 'ATV Grupo A', ['08:30']],
            [$atvTrails, 'ATV Grupo B', ['09:00', '13:00']],
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
            'La Cosita' => ['Luis Pech'],
            'Caguama' => ['Luis Pech'],
            'Laúd' => ['Luis Pech'],
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

    /** Crea el servicio (slug a partir del nombre, como en el panel), sus etapas y sus unidades habilitadas. */
    private function service(string $name, array $attrs, array $stages, array $assetNames): Service
    {
        $service = Service::query()->firstOrCreate(['slug' => Str::slug($name)], $attrs + ['name' => $name, 'currency' => Currency::USD]);
        foreach ($stages as $i => $stage) {
            $service->stages()->firstOrCreate(['position' => $i + 1], [
                'name' => $stage, 'requires_reading' => $i === count($stages) - 1,
            ]);
        }
        $service->assets()->syncWithoutDetaching(Asset::query()->whereIn('name', $assetNames)->pluck('id'));

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
                    $pay->handle($booking, $booking->total_cents, $booking->currency, PaymentMethod::Card);
                }
            });

        // Un privado de ejemplo pasado mañana a las 10:00.
        $create->handle(new BookingData(
            customer: ['name' => 'Familia Harris', 'email' => 'harris@example.com', 'country' => 'US'],
            adults: 6,
            channel: SalesChannel::Website,
            serviceId: Service::query()->where('slug', 'el-cielo-by-private-boat')->value('id'),
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
