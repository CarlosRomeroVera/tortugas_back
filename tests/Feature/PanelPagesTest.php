<?php

use App\Enums\BookingStatus;
use App\Enums\DepartureStatus;
use App\Filament\Resources\Bookings\Pages\CreateBooking;
use App\Filament\Resources\Bookings\Pages\ViewBooking;
use App\Filament\Resources\Departures\Pages\CreateDeparture;
use App\Filament\Resources\Departures\Pages\ViewDeparture;
use App\Filament\Widgets\FleetTimeline;
use App\Models\Asset;
use App\Models\Booking;
use App\Models\Departure;
use App\Models\MaintenanceOrder;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(); // roles + datos de ejemplo
    $this->actingAs(User::where('email', 'admin@tortugas.test')->first());
});

it('abre todas las pantallas del panel', function () {
    $boat = Asset::where('name', 'Carey I')->first();
    $van = Asset::where('name', 'Van 1')->first();
    $departure = Departure::first();
    $booking = Booking::first();
    $order = MaintenanceOrder::first();
    $service = Service::first();

    $urls = [
        '/admin',
        '/admin/embarcaciones', "/admin/embarcaciones/{$boat->id}", "/admin/embarcaciones/{$boat->id}/edit", '/admin/embarcaciones/create',
        '/admin/vehiculos', "/admin/vehiculos/{$van->id}",
        '/admin/salidas', "/admin/salidas/{$departure->id}", '/admin/salidas/create',
        '/admin/reservas', "/admin/reservas/{$booking->id}", "/admin/reservas/{$booking->id}/edit", '/admin/reservas/create',
        "/admin/reservas/create?departure={$departure->id}",
        '/admin/mantenimiento', "/admin/mantenimiento/{$order->id}", "/admin/mantenimiento/{$order->id}/edit", '/admin/mantenimiento/create',
        '/admin/servicios', "/admin/servicios/{$service->id}", "/admin/servicios/{$service->id}/edit", '/admin/servicios/create',
        '/admin/clientes', '/admin/personal', '/admin/ubicaciones',
    ];

    foreach ($urls as $url) {
        $this->get($url)->assertOk();
    }
});

it('una embarcación no aparece entre los vehículos', function () {
    $this->get('/admin/vehiculos/'.Asset::where('name', 'Carey I')->value('id'))->assertNotFound();
});

it('crea una reserva desde el asistente con los horarios disponibles', function () {
    $departure = Departure::whereHas('service', fn ($q) => $q->where('slug', 'snorkel-3-arrecifes'))->orderBy('starts_at')->first();

    Livewire::test(CreateBooking::class)
        ->fillForm([
            'service_id' => $departure->service_id,
            'date' => $departure->starts_at->tz('America/Cancun')->toDateString(),
            'adults' => 2,
            'children' => 1,
            'slot' => 'd:'.$departure->id,
            'customer_name' => 'Sarah Miller',
            'customer_email' => 'sarah@example.com',
            'channel' => 'whatsapp',
            'cruise_ship' => 'Wonder of the Seas',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Booking::where('cruise_ship', 'Wonder of the Seas')->first())
        ->pax->toBe(3)
        ->departure_id->toBe($departure->id);
});

it('reserva un privado eligiendo solo la hora', function () {
    $service = Service::where('slug', 'embarcacion-privada')->first();
    $date = CarbonImmutable::now('America/Cancun')->addDays(5)->toDateString();

    Livewire::test(CreateBooking::class)
        ->fillForm([
            'service_id' => $service->id,
            'date' => $date,
            'adults' => 4,
            'slot' => 'p:'.CarbonImmutable::parse("{$date} 09:00", 'America/Cancun')->utc()->toIso8601String(),
            'customer_name' => 'Familia Ortiz',
            'channel' => 'phone',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Booking::whereHas('customer', fn ($q) => $q->where('name', 'Familia Ortiz'))->first()->departure->asset->name)
        ->toBe('Laúd'); // la más chica que alcanza para 4
});

it('avisa sin romper cuando la embarcación ya está ocupada', function () {
    $departure = Departure::with('service', 'asset')->first();
    $before = Departure::count();

    Livewire::test(CreateDeparture::class)
        ->fillForm([
            'service_id' => $departure->service_id,
            'asset_id' => $departure->asset_id,
            'starts_at' => $departure->starts_at->tz('America/Cancun')->format('Y-m-d H:i:s'),
        ])
        ->call('create')
        ->assertNotified('No se pudo completar');

    expect(Departure::count())->toBe($before);
});

it('registra un pago y cancela desde la reserva', function () {
    $booking = Booking::where('status', BookingStatus::Pending)->first();

    Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
        ->callAction('pay', ['amount' => $booking->total_cents / 100, 'currency' => 'MXN', 'method' => 'cash'])
        ->assertNotified('Pago registrado');

    expect($booking->fresh()->status)->toBe(BookingStatus::Confirmed);

    Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
        ->callAction('cancel', ['reason' => 'Cierre de puerto'])
        ->assertNotified('Reserva cancelada');

    expect($booking->fresh()->status)->toBe(BookingStatus::Cancelled);
});

it('cancela una salida con sus reservas', function () {
    $departure = Departure::has('bookings')->where('status', DepartureStatus::Scheduled)->first();

    Livewire::test(ViewDeparture::class, ['record' => $departure->getRouteKey()])
        ->callAction('cancel', ['reason' => 'Capitanía cerró el puerto']);

    expect($departure->fresh()->status)->toBe(DepartureStatus::Cancelled)
        ->and($departure->bookings()->occupyingSeats()->count())->toBe(0)
        ->and($departure->allocation->fresh()->status->value)->toBe('released');
});

it('la agenda muestra las salidas del día elegido', function () {
    $departure = Departure::with('service')->orderBy('starts_at')->first();

    Livewire::test(FleetTimeline::class)
        ->set('date', $departure->starts_at->tz('America/Cancun')->toDateString())
        ->assertSee($departure->service->name)
        ->assertSee('Carey I');
});
