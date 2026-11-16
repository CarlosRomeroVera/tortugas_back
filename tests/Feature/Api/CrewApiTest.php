<?php

use App\Actions\Bookings\BookingData;
use App\Actions\Bookings\CreateBooking;
use App\Actions\Crew\AssignCrew;
use App\Enums\BookingStatus;
use App\Enums\DepartureStatus;
use App\Enums\FaultStatus;
use App\Enums\StaffRole;
use App\Filament\Resources\Assets\RelationManagers\FaultReportsRelationManager;
use App\Filament\Resources\Boats\Pages\ViewBoat;
use App\Filament\Resources\Departures\Pages\ViewDeparture;
use App\Filament\Resources\Departures\RelationManagers\CrewRelationManager;
use App\Filament\Widgets\OpsAlerts;
use App\Models\Asset;
use App\Models\Departure;
use App\Models\FaultReport;
use App\Models\FieldEvent;
use App\Models\Location;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->captain = crewUser();
    $this->service = serviceWithStages();
    $this->boat = Asset::factory()->capacity(10)->create(['name' => 'Carey I', 'engine_hours' => 1812]);
    $this->departure = scheduleDeparture($this->service, $this->boat, '10:00');
    app(AssignCrew::class)->handle($this->departure, $this->captain->staff);

    $pickup = Location::create(['name' => 'Hotel Presidente', 'type' => 'hotel', 'latitude' => 20.4675, 'longitude' => -86.98]);
    $this->booking = app(CreateBooking::class)->handle(new BookingData(
        customer: ['name' => 'Emily Carter', 'phone' => '+15551234'], adults: 2, children: 1,
        departureId: $this->departure->id, cruiseShip: 'Icon of the Seas',
        allAboardAt: tomorrowAt('17:30'), pickupLocationId: $pickup->id,
    ));
    $this->other = app(CreateBooking::class)->handle(new BookingData(
        customer: ['name' => 'Daniel Ruiz'], adults: 1, departureId: $this->departure->id,
    ));

    $this->date = $this->departure->starts_at->tz('America/Cancun')->toDateString();
    $this->stage = fn (int $position) => $this->service->stages->firstWhere('position', $position)->id;

    Sanctum::actingAs($this->captain);
});

/** Arma un evento con valores por omisión. */
function fieldEvent(string $type, array $attrs = []): array
{
    return $attrs + ['client_uuid' => (string) Str::uuid(), 'type' => $type, 'occurred_at' => now()->toIso8601String()];
}

function syncEvents(array ...$events)
{
    return test()->postJson('/api/v1/sync/events', ['events' => $events]);
}

describe('perfil y catálogo', function () {
    it('devuelve el usuario con sus roles y su ficha de personal', function () {
        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.roles', ['capitan'])
            ->assertJsonPath('data.staff.name', 'Rubén Canul')
            ->assertJsonPath('data.staff.role', 'captain');
    });

    it('lista los servicios activos con sus etapas', function () {
        $this->service->replicate()->fill(['slug' => 'inactivo', 'is_active' => false])->save();

        $this->getJson('/api/v1/services')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', $this->service->slug)
            ->assertJsonPath('data.0.stages.3.requires_reading', true);
    });
});

describe('mis salidas', function () {
    it('lista solo las salidas donde voy como tripulación', function () {
        scheduleDeparture($this->service, Asset::factory()->create(), '10:00'); // sin tripulación

        $this->getJson("/api/v1/me/departures?date={$this->date}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->departure->id)
            ->assertJsonPath('data.0.my_role', 'captain')
            ->assertJsonPath('data.0.local_time', '10:00')
            ->assertJsonPath('data.0.pax.booked', 4)
            ->assertJsonPath('data.0.asset.reading.unit', 'hours')
            ->assertJsonPath('data.0.stages_total', 4)
            ->assertJsonMissingPath('data.0.manifest');
    });

    it('filtra por fecha local', function () {
        $this->getJson('/api/v1/me/departures?date='.now('America/Cancun')->subDay()->toDateString())
            ->assertOk()->assertJsonCount(0, 'data');
    });

    it('muestra el manifiesto con recogida y crucero', function () {
        $this->getJson("/api/v1/me/departures/{$this->departure->id}")
            ->assertOk()
            ->assertJsonCount(4, 'data.stages')
            ->assertJsonPath('data.stages.0.done', false)
            ->assertJsonCount(2, 'data.manifest')
            ->assertJsonPath('data.manifest.1.code', $this->booking->code) // ordenado por punto de recogida
            ->assertJsonPath('data.manifest.1.pickup.name', 'Hotel Presidente')
            ->assertJsonPath('data.manifest.1.all_aboard_local_time', '17:30')
            ->assertJsonPath('data.manifest.1.balance_cents', 250000)
            ->assertJsonPath('data.manifest.1.customer.phone', '+15551234');
    });

    it('no deja ver la salida de otra tripulación', function () {
        $foreign = scheduleDeparture($this->service, Asset::factory()->create(), '15:00');

        $this->getJson("/api/v1/me/departures/{$foreign->id}")->assertNotFound();
    });

    it('pide rol de capitán o chofer', function () {
        Sanctum::actingAs(userWithRole('ventas'));

        $this->getJson("/api/v1/me/departures?date={$this->date}")->assertForbidden();
        syncEvents(fieldEvent('check_in', ['booking_id' => $this->booking->id]))->assertForbidden();
    });

    it('avisa si el usuario no tiene ficha de personal', function () {
        Sanctum::actingAs(userWithRole('chofer'));

        $this->getJson('/api/v1/me/departures')
            ->assertForbidden()
            ->assertJsonPath('message', 'Tu usuario no está ligado a una ficha de personal. Pide a coordinación que lo vincule.');
    });
});

describe('sincronización de etapas', function () {
    it('inicia la salida con la primera etapa', function () {
        syncEvents(fieldEvent('stage', ['departure_id' => $this->departure->id, 'stage_id' => ($this->stage)(1), 'latitude' => 20.4231, 'longitude' => -86.9228]))
            ->assertOk()
            ->assertJsonPath('results.0.status', 'applied');

        expect($this->departure->fresh())
            ->status->toBe(DepartureStatus::InProgress)
            ->departed_at->not->toBeNull()
            ->current_stage_id->toBe(($this->stage)(1));
        expect(FieldEvent::first()->latitude)->toEqual('20.423100');
    });

    it('pide la lectura en la etapa de cierre y termina la salida', function () {
        syncEvents(fieldEvent('stage', ['departure_id' => $this->departure->id, 'stage_id' => ($this->stage)(4)]))
            ->assertJsonPath('results.0.status', 'rejected')
            ->assertJsonPath('results.0.reason', 'reading_required');

        syncEvents(fieldEvent('stage', ['departure_id' => $this->departure->id, 'stage_id' => ($this->stage)(4), 'reading' => 1800]))
            ->assertJsonPath('results.0.reason', 'invalid_reading');

        syncEvents(fieldEvent('check_in', ['booking_id' => $this->booking->id, 'occurred_at' => now()->subMinutes(5)->toIso8601String()]),
            fieldEvent('stage', ['departure_id' => $this->departure->id, 'stage_id' => ($this->stage)(4), 'reading' => 1814.5]))
            ->assertJsonPath('results.0.status', 'applied')
            ->assertJsonPath('results.1.status', 'applied');

        expect($this->departure->fresh())->status->toBe(DepartureStatus::Completed)->returned_at->not->toBeNull();
        expect((float) $this->boat->fresh()->engine_hours)->toBe(1814.5);
        expect($this->booking->fresh()->status)->toBe(BookingStatus::Completed);
        expect($this->other->fresh()->status)->toBe(BookingStatus::Pending); // no abordó: lo revisa coordinación
    });

    it('aplica en orden de occurred_at y no regresa etapas', function () {
        $late = now()->subMinutes(30);

        syncEvents(
            fieldEvent('stage', ['departure_id' => $this->departure->id, 'stage_id' => ($this->stage)(3), 'occurred_at' => $late->copy()->addMinutes(20)->toIso8601String()]),
            fieldEvent('stage', ['departure_id' => $this->departure->id, 'stage_id' => ($this->stage)(2), 'occurred_at' => $late->toIso8601String()]),
        )
            ->assertJsonPath('results.0.status', 'applied')
            ->assertJsonPath('results.1.status', 'applied');

        // Llega tarde un evento de una etapa anterior (otro teléfono sin señal).
        syncEvents(fieldEvent('stage', ['departure_id' => $this->departure->id, 'stage_id' => ($this->stage)(1), 'occurred_at' => $late->copy()->subMinutes(10)->toIso8601String()]))
            ->assertJsonPath('results.0.status', 'ignored');

        expect($this->departure->fresh()->current_stage_id)->toBe(($this->stage)(3));
        expect($this->departure->fresh()->departed_at->timestamp)->toBe($late->timestamp);

        $this->getJson("/api/v1/me/departures/{$this->departure->id}")
            ->assertJsonPath('data.stages.1.done', true)
            ->assertJsonPath('data.stages.1.reached_at', $late->utc()->startOfSecond()->toIso8601String())
            ->assertJsonPath('data.stages.0.reached_at', null);
    });

    it('es idempotente por client_uuid', function () {
        $e = fieldEvent('stage', ['departure_id' => $this->departure->id, 'stage_id' => ($this->stage)(1)]);

        syncEvents($e)->assertJsonPath('results.0.status', 'applied');
        syncEvents($e)->assertJsonPath('results.0.status', 'duplicate');

        expect(FieldEvent::count())->toBe(1);
    });

    it('rechaza eventos de salidas ajenas o canceladas', function () {
        $foreign = scheduleDeparture($this->service, Asset::factory()->create(), '15:00');
        $this->departure->update(['status' => DepartureStatus::Cancelled]);

        syncEvents(
            fieldEvent('stage', ['departure_id' => $foreign->id, 'stage_id' => ($this->stage)(1)]),
            fieldEvent('stage', ['departure_id' => $this->departure->id, 'stage_id' => ($this->stage)(1)]),
            fieldEvent('stage', ['departure_id' => (string) Str::uuid(), 'stage_id' => ($this->stage)(1)]),
        )
            ->assertJsonPath('results.0.reason', 'not_crew')
            ->assertJsonPath('results.1.reason', 'departure_cancelled')
            ->assertJsonPath('results.2.reason', 'not_found');

        expect(FieldEvent::count())->toBe(0);
    });

    it('valida la forma del lote', function () {
        syncEvents(['client_uuid' => 'no-es-uuid', 'type' => 'stage', 'occurred_at' => 'ayer'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['events.0.client_uuid', 'events.0.occurred_at', 'events.0.departure_id', 'events.0.stage_id']);

        syncEvents(fieldEvent('fault'))->assertJsonValidationErrors(['events.0.asset_id', 'events.0.severity', 'events.0.description']);
    });

    it('no acepta horas en el futuro', function () {
        syncEvents(fieldEvent('stage', ['departure_id' => $this->departure->id, 'stage_id' => ($this->stage)(1), 'occurred_at' => now()->addHours(3)->toIso8601String()]));

        expect($this->departure->fresh()->departed_at->lte(now()))->toBeTrue();
    });
});

describe('abordaje y no presentados', function () {
    it('registra, deshace y vuelve a registrar el abordaje', function () {
        syncEvents(fieldEvent('check_in', ['booking_id' => $this->booking->id]))->assertJsonPath('results.0.status', 'applied');
        expect($this->booking->fresh()->checked_in_at)->not->toBeNull()
            ->and($this->booking->fresh()->checked_in_by)->toBe($this->captain->id);

        syncEvents(fieldEvent('check_in', ['booking_id' => $this->booking->id]))->assertJsonPath('results.0.status', 'ignored');

        syncEvents(fieldEvent('check_in_undo', ['booking_id' => $this->booking->id, 'occurred_at' => now()->addSecond()->toIso8601String()]))
            ->assertJsonPath('results.0.status', 'applied');
        expect($this->booking->fresh()->checked_in_at)->toBeNull();

        $this->getJson("/api/v1/me/departures?date={$this->date}")->assertJsonPath('data.0.pax.checked_in', 0);
    });

    it('gana el deshacer más reciente aunque llegue primero', function () {
        $t = now()->subMinutes(10);

        syncEvents(fieldEvent('check_in_undo', ['booking_id' => $this->booking->id, 'occurred_at' => $t->copy()->addMinute()->toIso8601String()]))
            ->assertJsonPath('results.0.status', 'ignored');
        syncEvents(fieldEvent('check_in', ['booking_id' => $this->booking->id, 'occurred_at' => $t->toIso8601String()]))
            ->assertJsonPath('results.0.status', 'ignored');

        expect($this->booking->fresh()->checked_in_at)->toBeNull();
    });

    it('marca no presentado y libera el lugar', function () {
        syncEvents(fieldEvent('no_show', ['booking_id' => $this->other->id]))->assertJsonPath('results.0.status', 'applied');

        expect($this->other->fresh()->status)->toBe(BookingStatus::NoShow)
            ->and($this->departure->fresh()->seatsTaken())->toBe(3);

        // Llegó tarde: al registrarlo regresa a su estado anterior.
        syncEvents(fieldEvent('check_in', ['booking_id' => $this->other->id]));
        expect($this->other->fresh()->status)->toBe(BookingStatus::Pending);
    });

    it('no marca no presentado a quien ya abordó ni toca reservas canceladas', function () {
        syncEvents(fieldEvent('check_in', ['booking_id' => $this->booking->id]));
        $this->other->update(['status' => BookingStatus::Cancelled]);

        syncEvents(fieldEvent('no_show', ['booking_id' => $this->booking->id]), fieldEvent('check_in', ['booking_id' => $this->other->id]))
            ->assertJsonPath('results.0.reason', 'checked_in')
            ->assertJsonPath('results.1.reason', 'booking_cancelled');
    });

    it('sirve igual para el chofer en un servicio con vehículo', function () {
        $driver = crewUser('chofer', StaffRole::Driver, 'Pedro Uc');
        $jeep = Asset::factory()->vehicle()->create(['odometer_km' => 88045]);
        $tour = scheduleDeparture(serviceWithStages(['Recogida', 'Punta Sur', 'Regreso'], ['asset_kind' => 'vehicle']), $jeep, '09:00');
        app(AssignCrew::class)->handle($tour, $driver->staff);
        Sanctum::actingAs($driver);

        $this->getJson("/api/v1/me/departures/{$tour->id}")
            ->assertOk()
            ->assertJsonPath('data.my_role', 'driver')
            ->assertJsonPath('data.asset.reading.unit', 'km');

        syncEvents(fieldEvent('stage', ['departure_id' => $tour->id, 'stage_id' => $tour->service->stages->last()->id, 'reading' => 88112.4]))
            ->assertJsonPath('results.0.status', 'applied');

        expect($jeep->fresh()->odometer_km)->toBe(88112);
    });
});

describe('fallas', function () {
    it('registra una falla para coordinación', function () {
        syncEvents(fieldEvent('fault', [
            'asset_id' => $this->boat->id, 'departure_id' => $this->departure->id,
            'severity' => 'blocking', 'description' => 'La bomba de achique no arranca',
        ]))->assertJsonPath('results.0.status', 'applied');

        expect(FaultReport::sole())
            ->status->toBe(FaultStatus::Open)
            ->reported_by->toBe($this->captain->id)
            ->departure_id->toBe($this->departure->id);
    });
});

it('se ve la tripulación en el panel', function () {
    $this->seed();
    $this->actingAs(User::where('email', 'admin@tortugas.test')->first());

    $departure = Departure::whereHas('crew')->first();
    $staff = Staff::create(['name' => 'Capitán suplente', 'role' => StaffRole::Captain]);
    $free = Departure::whereHas('asset', fn ($q) => $q->where('name', 'Tortuga'))->first();

    Livewire\Livewire::test(CrewRelationManager::class, [
        'ownerRecord' => $departure, 'pageClass' => ViewDeparture::class,
    ])->assertOk()->assertCanSeeTableRecords($departure->crew);

    Livewire\Livewire::test(CrewRelationManager::class, [
        'ownerRecord' => $free, 'pageClass' => ViewDeparture::class,
    ])
        ->callTableAction('assign', data: ['staff_id' => $staff->id, 'role' => 'captain'])
        ->assertHasNoTableActionErrors()->assertNotified('Tripulación asignada');

    expect($free->crew()->whereKey($staff->id)->exists())->toBeTrue();
});

it('coordinación ve y atiende las fallas reportadas', function () {
    syncEvents(fieldEvent('fault', ['asset_id' => $this->boat->id, 'severity' => 'blocking', 'description' => 'Fuga de aceite']));
    $this->actingAs(userWithRole('coordinacion'));
    $fault = FaultReport::sole();

    Livewire\Livewire::test(OpsAlerts::class)->assertSee('Carey I: falla que impide operar');

    Livewire\Livewire::test(FaultReportsRelationManager::class, [
        'ownerRecord' => $this->boat, 'pageClass' => ViewBoat::class,
    ])
        ->assertCanSeeTableRecords([$fault])
        ->callTableAction('resolve', $fault);

    expect($fault->fresh()->status)->toBe(FaultStatus::Resolved);
    Livewire\Livewire::test(OpsAlerts::class)->assertDontSee('falla que impide operar');
});
