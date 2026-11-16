# tortugas_back

Backoffice y API de **Tortugas Cozumel**: reservas, disponibilidad de embarcaciones y vehículos, salidas, pagos y mantenimiento.

- Laravel 12 · PHP 8.4 · Filament 4 (panel en `/admin`)
- MySQL 8 (DBngin) · Sanctum (API `/api/v1`)
- Pest (incluye pruebas de concurrencia con procesos reales)

## Arranque local

```bash
# MySQL 8 local (p. ej. DBngin en 127.0.0.1:3306, root sin contraseña); crear las bases:
mysql -h127.0.0.1 -uroot --ssl-mode=DISABLED -e "CREATE DATABASE tortugas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE tortugas_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
# Alternativa sin MySQL local: docker compose up -d (MySQL 8.4 en 3307; en .env usar DB_PORT=3307, DB_USERNAME=tortugas, DB_PASSWORD=secret)
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve             # http://localhost:8000/admin
```

Usuarios de ejemplo (contraseña `password`): `admin@`, `coordinacion@`, `ventas@`, `capitan@`, `chofer@` + `tortugas.test`.
Capitán y chofer no entran al panel; usan la API (app Ionic).

```bash
./vendor/bin/pest             # toda la suite (usa la base tortugas_test)
php artisan departures:generate --days=14   # crea salidas compartidas desde los horarios fijos
```

## Reglas que no hay que romper

- **Fechas en UTC** (`DATETIME`, `APP_TIMEZONE=UTC`); se muestran en `America/Cancun`. Usa `App\Support\LocalTime`.
- **Dinero en centavos** (enteros). Cada pago guarda el tipo de cambio pesos/USD del día.
- **Toda ocupación de una embarcación o vehículo se crea con `App\Actions\Allocations\AllocateAsset`.**
  MySQL no tiene restricciones de exclusión: esa Action bloquea la fila del recurso (`lockForUpdate`) y revisa traslapes.
  `tests/Feature/ArchitectureTest.php` falla si alguien crea una `Allocation` por otro camino.
- El cupo de salidas compartidas lo protege `CreateBooking` bloqueando la fila de la salida.
- Filament y la API llaman a las mismas Actions (`app/Actions`). Los errores de negocio (`App\Exceptions\DomainException`)
  salen como 409/422 en la API y como notificación en el panel.

## API v1

| Método | Ruta | Uso |
|---|---|---|
| POST | `/api/v1/auth/token` | Correo, contraseña y `device_name` → token Sanctum |
| GET | `/api/v1/availability?service={slug}&date=AAAA-MM-DD&pax=N` | Horarios con lugares (compartidos) o unidades libres (privados) |
| POST | `/api/v1/bookings` | Crea reserva. Header `Idempotency-Key` evita duplicados (201 nueva, 200 repetida, 409 sin lugar) |
| GET | `/api/v1/bookings/{code}` | Detalle con saldo |
| POST | `/api/v1/bookings/{code}/payments` | Registra pago (MXN o USD) |
| POST | `/api/v1/bookings/{code}/cancel` | Cancela y libera lugares |

## Fases

1. **Catálogos, reservas y disponibilidad** ← este código
2. Salidas en vivo: etapas, app del capitán (sincronización offline), Reverb
3. Recogidas, corridas de vehículos, GPS
4. Mantenimiento preventivo automático, cierres de puerto, reportes
