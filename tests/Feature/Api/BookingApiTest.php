<?php

use App\Models\Asset;
use App\Models\Booking;
use App\Models\Service;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->service = Service::factory()->create(['slug' => 'snorkel']);
    $this->departure = scheduleDeparture($this->service, Asset::factory()->capacity(6)->create(), '10:00');
    $this->payload = [
        'departure_id' => $this->departure->id,
        'adults' => 2,
        'children' => 1,
        'channel' => 'website',
        'customer' => ['name' => 'Emily Carter', 'email' => 'emily@example.com'],
        'cruise_ship' => 'Icon of the Seas',
    ];
});

it('pide autenticación', function () {
    $this->getJson('/api/v1/availability?service=snorkel&date=2026-12-01&pax=2')->assertUnauthorized();
    $this->postJson('/api/v1/bookings', $this->payload)->assertUnauthorized();
});

it('entrega un token con correo y contraseña', function () {
    $user = userWithRole('capitan');

    $this->postJson('/api/v1/auth/token', ['email' => $user->email, 'password' => 'password', 'device_name' => 'iPhone Rubén'])
        ->assertCreated()
        ->assertJsonPath('user.roles', ['capitan'])
        ->assertJsonStructure(['token']);
});

it('consulta disponibilidad', function () {
    Sanctum::actingAs(userWithRole('ventas'));

    $this->getJson('/api/v1/availability?service=snorkel&date='.$this->departure->starts_at->tz('America/Cancun')->toDateString().'&pax=2')
        ->assertOk()
        ->assertJsonPath('service.shared', true)
        ->assertJsonPath('slots.0.departure_id', $this->departure->id)
        ->assertJsonPath('slots.0.seats_left', 6);
});

it('crea una reserva y la repite sin duplicar con Idempotency-Key', function () {
    Sanctum::actingAs(userWithRole('ventas'));

    $first = $this->postJson('/api/v1/bookings', $this->payload, ['Idempotency-Key' => 'web-8812'])
        ->assertCreated()
        ->assertJsonPath('data.pax', 3)
        ->assertJsonPath('data.total_cents', 250000)
        ->assertJsonPath('data.balance_cents', 250000)
        ->assertJsonPath('data.departure.local_time', $this->departure->starts_at->tz('America/Cancun')->format('Y-m-d H:i'));

    $this->postJson('/api/v1/bookings', $this->payload, ['Idempotency-Key' => 'web-8812'])
        ->assertOk()
        ->assertJsonPath('data.id', $first->json('data.id'));

    expect(Booking::count())->toBe(1);
});

it('responde 409 cuando ya no hay lugar', function () {
    Sanctum::actingAs(userWithRole('ventas'));

    $this->postJson('/api/v1/bookings', [...$this->payload, 'adults' => 7, 'children' => 0])
        ->assertStatus(409)
        ->assertJsonPath('message', 'No hay lugar para 7 personas: quedan 6.');
});

it('valida los datos', function () {
    Sanctum::actingAs(userWithRole('ventas'));

    $this->postJson('/api/v1/bookings', ['adults' => 0, 'channel' => 'fax'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['adults', 'channel', 'customer.name', 'departure_id']);
});

it('no deja reservar a un capitán', function () {
    Sanctum::actingAs(userWithRole('capitan'));

    $this->postJson('/api/v1/bookings', $this->payload)->assertForbidden();
});

it('registra un pago en dólares y confirma la reserva', function () {
    Sanctum::actingAs(userWithRole('ventas'));
    $code = $this->postJson('/api/v1/bookings', $this->payload)->json('data.code');

    $this->postJson("/api/v1/bookings/{$code}/payments", [
        'amount_cents' => 10000, 'currency' => 'USD', 'method' => 'card', 'usd_rate' => 18,
    ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'confirmed')
        ->assertJsonPath('data.paid_cents', 180000)
        ->assertJsonPath('data.balance_cents', 70000);
});

it('cancela una reserva', function () {
    Sanctum::actingAs(userWithRole('coordinacion'));
    $code = $this->postJson('/api/v1/bookings', $this->payload)->json('data.code');

    $this->postJson("/api/v1/bookings/{$code}/cancel", ['reason' => 'Cierre de puerto'])
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');
});
