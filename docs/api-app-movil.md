# API de Tortugas Ops para la app móvil (Ionic)

Notas para el código de la app. Backend: Laravel 12 + Sanctum, repo `tortugas_back`.
Estado: fase 1 (15-nov-2026) + API de capitán y chofer (16-nov-2026). Las secciones 1 a 6 se verificaron contra el código y con llamadas reales; la sección 7 (capitán y chofer), contra el código y con pruebas automáticas (`tests/Feature/Api/CrewApiTest.php`).

## 1. Lo más importante

- Base URL: `{HOST}/api/v1`
- Autenticación: token Bearer (Sanctum). No hay cookies ni CSRF.
- Envía **siempre** `Accept: application/json`. Sin ese header, los errores pueden regresar HTML o redirecciones.
- Dinero en **centavos enteros** (`138000` = $1,380.00). Nunca uses flotantes.
- Fechas en **UTC ISO-8601** (`2026-11-16T13:30:00+00:00`). La hora local de operación es `America/Cancun` (UTC−5, sin horario de verano). Muchas respuestas ya traen `local_time` listo para mostrar.
- IDs en UUID (string). Excepción: `user.id`, que es entero.
- **Alcance actual:** login, perfil, catálogo de servicios, disponibilidad, reservas y pagos (reservas y pagos solo para oficina) y la operación de capitán y chofer: sus salidas, manifiesto, abordaje, etapas, lecturas y fallas (sección 7).

## 2. Entornos

| Entorno | Base URL |
|---|---|
| Local, navegador en la misma Mac (`ionic serve`) | `http://localhost:8000/api/v1` |
| Emulador Android | `http://10.0.2.2:8000/api/v1` |
| Teléfono físico en la misma red | `http://<IP-de-la-Mac>:8000/api/v1`. El backend debe arrancar con `php artisan serve --host=0.0.0.0` |
| Producción | Por definir |

Guarda la base URL en `environment.ts`, no la escribas directamente en el código.

- **Android y HTTP:** Android bloquea el tráfico HTTP sin cifrar por defecto. En desarrollo hay que permitirlo (`android:usesCleartextTraffic="true"` o configurar `server.cleartext` en Capacitor). En producción se usará HTTPS.
- **CORS:** el backend usa la configuración por defecto de Laravel (`api/*`, cualquier origen). Como la app autentica con token Bearer y no con cookies, no hace falta configurar nada más.

## 3. Autenticación

### Iniciar sesión: `POST /auth/token`

Pública. Límite: 10 intentos por minuto (después responde 429).

```json
{ "email": "capitan@tortugas.test", "password": "password", "device_name": "iPhone de Rubén" }
```

`device_name` es obligatorio (máx. 100 caracteres). Úsalo para identificar el dispositivo, por ejemplo con el modelo de `@capacitor/device`.

**201**
```json
{
  "token": "1|rTZvRDYWfAXI37YB57HQtVnqhnZrkRHIe9vERRoV9a71360e",
  "user": { "id": 4, "name": "Rubén Canul", "roles": ["capitan"] }
}
```

**422** si las credenciales no son válidas:
```json
{ "message": "Correo o contraseña incorrectos.", "errors": { "email": ["Correo o contraseña incorrectos."] } }
```

- **Expiración:** el token no caduca solo. Dura hasta que se cierra sesión o alguien lo revoca.
- **Almacenamiento:** guárdalo en almacenamiento seguro (Keychain/Keystore, por ejemplo con `capacitor-secure-storage`), no en `localStorage`.
- **Uso:** envíalo en cada petición con `Authorization: Bearer <token>`. Lo más práctico es un interceptor HTTP.
- **401:** si cualquier llamada responde 401, borra el token y regresa al login.
- **Roles:** guarda `user.roles` para decidir qué pantallas mostrar. Los roles son: `admin`, `coordinacion`, `ventas`, `capitan`, `chofer`, `mantenimiento`.

### Cerrar sesión: `DELETE /auth/token`

Revoca solo el token del dispositivo actual y responde **204**.

### Usuarios de prueba (contraseña `password`)

`admin@tortugas.test`, `coordinacion@tortugas.test`, `ventas@tortugas.test`, `capitan@tortugas.test`, `chofer@tortugas.test`

## 4. Endpoints

Todos requieren el token. La columna "Quién" indica los roles que pueden usarlo; a los demás la API les responde 403.

| Método | Ruta | Quién |
|---|---|---|
| GET | `/me` | Cualquier usuario autenticado |
| GET | `/services` | Cualquier usuario autenticado |
| GET | `/availability` | Cualquier usuario autenticado |
| GET | `/me/departures?date=` | capitan, chofer |
| GET | `/me/departures/{id}` | capitan, chofer |
| POST | `/sync/events` | capitan, chofer |
| POST | `/bookings` | admin, coordinacion, ventas |
| GET | `/bookings/{code}` | admin, coordinacion, ventas |
| POST | `/bookings/{code}/cancel` | admin, coordinacion, ventas |
| POST | `/bookings/{code}/payments` | admin, coordinacion, ventas |

`{code}` es el código legible de la reserva (empieza con `R`), no el UUID.

### GET `/availability?service=&date=&pax=`

- `service`: slug o UUID de un servicio activo. Slugs de prueba: `snorkel-3-arrecifes`, `parasailing`, `jeep-tour` (compartidos) y `embarcacion-privada` (privado).
- `date`: fecha local en formato `YYYY-MM-DD`.
- `pax`: número de personas, de 1 a 100.

Si el servicio no existe responde 404.

**Servicio compartido:** cada horario es una salida ya programada con lugares que se venden por persona.
```json
{
  "service": { "id": "…", "slug": "snorkel-3-arrecifes", "name": "Snorkel 3 arrecifes", "shared": true,
               "duration_minutes": 150, "currency": "MXN", "price_adult_cents": 138000, "price_child_cents": 99000 },
  "date": "2026-11-16",
  "timezone": "America/Cancun",
  "slots": [
    { "departure_id": "…", "starts_at": "2026-11-16T13:30:00+00:00", "local_time": "08:30",
      "asset": "Carey I", "capacity": 24, "seats_left": 23, "available": true }
  ]
}
```
`available: false` significa que no alcanzan los lugares para `pax`. Muestra el horario deshabilitado.

**Servicio privado** (`shared: false`): la embarcación o el vehículo se reserva completo. Los horarios son horas de inicio cada 30 minutos, entre 08:00 y 18:00 hora local, en las que hay alguna unidad libre:
```json
{ "starts_at": "2026-11-16T14:00:00+00:00", "local_time": "09:00",
  "assets": [ { "id": "…", "name": "Laúd", "capacity": 8 } ], "available": true }
```
Esta consulta no aparta nada. El lugar solo queda asegurado cuando se crea la reserva.

### POST `/bookings`

Envía el header `Idempotency-Key: <uuid v4>` (máx. 100 caracteres). Genera una clave por cada intento de reserva y reutilízala en los reintentos. Si la petición se repite con la misma clave, la API devuelve la misma reserva con **200** en lugar de crear otra (la primera vez responde **201**). Esto evita reservas dobles cuando la señal es mala.

```json
{
  "departure_id": "…",
  "adults": 2,
  "children": 1,
  "channel": "counter",
  "customer": { "name": "Ana López", "email": "ana@example.com", "phone": "+529871234567", "country": "MX" },
  "cruise_ship": "Symphony of the Seas",
  "all_aboard_at": "2026-11-16T22:30:00Z",
  "pickup_location_id": null,
  "agency_reference": null,
  "notes": null
}
```

- **Compartido:** manda `departure_id`, que viene en los horarios de `/availability`.
- **Privado:** en lugar de `departure_id`, manda `service_id` + `starts_at` (UTC). `asset_id` es opcional; si no lo mandas, se asigna la unidad más chica que alcance.
- **Obligatorios:** `adults` (mínimo 1), `channel` y `customer.name`. `customer.country` va en código ISO de 2 letras.
- **`channel`:** `website` | `whatsapp` | `agency` | `counter` | `phone`.
- **Precio:** lo calcula el servidor. No mandes montos.
- **Estado:** la reserva se crea como `pending` y pasa a `confirmed` con el primer pago.

Respuesta: objeto `data` con el formato de reserva (abajo).

### GET `/bookings/{code}`

Devuelve `data` con el formato de reserva.

### POST `/bookings/{code}/cancel`

Cuerpo: `{ "reason": "Clima" }` (opcional, máx. 500 caracteres). Si el servicio es privado, también libera la unidad. Responde 200 con `data`.

### POST `/bookings/{code}/payments`

```json
{ "amount_cents": 50000, "currency": "MXN", "method": "cash", "usd_rate": 18.0, "provider": null, "provider_reference": null }
```

- **`currency`:** `MXN` | `USD`.
- **`method`:** `cash` | `card` | `transfer` | `online` | `agency_credit`.
- **`usd_rate`:** pesos por dólar. Solo se usa si pagan en USD; si no se manda, el servidor usa 18.00.
- **Restricción:** no se puede registrar un pago en una reserva cancelada.

Responde **201** con `data`, la reserva actualizada.

### Formato de reserva (`data`)

```json
{
  "id": "…", "code": "R-7KQ2MX", "status": "confirmed", "channel": "counter",
  "adults": 2, "children": 1, "pax": 3,
  "currency": "MXN", "total_cents": 375000, "paid_cents": 50000, "balance_cents": 325000,
  "cruise_ship": null, "all_aboard_at": null,
  "customer": { "name": "Ana López", "email": "ana@example.com", "phone": "+529871234567" },
  "departure": { "id": "…", "code": "…", "service": "Snorkel 3 arrecifes", "asset": "Carey I",
                 "starts_at": "2026-11-16T13:30:00+00:00", "local_time": "2026-11-16 08:30" },
  "payments": [ { "amount_cents": 50000, "currency": "MXN", "method": "cash", "status": "paid", "paid_at": "…" } ]
}
```

- **`status` de la reserva:** `pending` | `confirmed` | `cancelled` | `no_show` | `completed`.
- **`status` de un pago:** `pending` | `paid` | `failed` | `refunded`.
- **Saldos:** `paid_cents` y `balance_cents` ya vienen convertidos a la moneda de la reserva. No los recalcules en la app.

## 5. Errores

| Código | Cuándo | Cuerpo |
|---|---|---|
| 401 | Sin token o token revocado | `{ "message": "Unauthenticated." }` → regresar al login |
| 403 | El rol no tiene permiso | `{ "message": "This action is unauthorized." }`. En las rutas de capitán y chofer: `"User does not have the right roles."` |
| 404 | La reserva o el servicio no existe | `{ "message": "…" }` |
| 409 | Conflicto de negocio: sin lugares, unidad ocupada o fuera de servicio | `{ "message": "<texto en español para el usuario>" }` |
| 422 | Datos inválidos o regla de negocio (salida ya no admite reservas, reserva cancelada…) | `{ "message": "…", "errors": { "campo": ["…"] } }`. Las reglas de negocio no traen `errors` |
| 429 | Demasiados intentos de login | Espera un minuto |

- **Mensajes:** los de 409 y 422 están escritos para mostrarse tal cual al usuario.
- **409:** ante un 409, vuelve a consultar `/availability`, porque otro vendedor pudo haber ocupado el lugar.
- **Formulario:** si viene `errors`, pinta cada mensaje junto a su campo.
- **Errores 500:** en desarrollo (`APP_DEBUG=true`) la respuesta trae el stack trace. No lo muestres en la interfaz.

## 6. Recomendaciones para la app

- **Interceptor HTTP único:** que agregue la base URL, `Accept: application/json` y `Authorization`, y que maneje 401 → logout.
- **Horas:** muestra `local_time` cuando venga. Si tienes que convertir tú, usa `America/Cancun`, no la zona horaria del teléfono, porque muchos clientes son turistas o cruceristas con el reloj en otra zona.
- **Montos:** usa `Intl.NumberFormat('es-MX', { style: 'currency', currency })` sobre `cents / 100`.
- **Idempotencia:** genera la `Idempotency-Key` al abrir el resumen de la reserva y no la cambies en los reintentos.

## 7. App del capitán y del chofer

Capitán y chofer usan **los mismos endpoints**. La diferencia está en la unidad: el capitán opera una embarcación (lectura en **horas de motor**) y el chofer un vehículo (lectura en **km**). Cada usuario solo ve las salidas donde coordinación lo asignó como tripulación, desde el panel: *Salidas → (salida) → Tripulación → Asignar*.

Requisitos para que un usuario vea algo:

1. Tiene rol `capitan` o `chofer`. Si no, la API responde 403.
2. Su usuario está ligado a una ficha de **Personal** (`staff`). Si no, `/me/departures` responde 403 con el mensaje `"Tu usuario no está ligado a una ficha de personal. Pide a coordinación que lo vincule."`.
3. Está en la tripulación de la salida.

Con los datos de prueba (`php artisan migrate:fresh --seed`), `capitan@tortugas.test` (Rubén Canul) va en las salidas de **Carey I** y `chofer@tortugas.test` (Pedro Uc) en las de **Jeep 2**. El seeder crea salidas **a partir de mañana**, así que manda `?date=` con la fecha de mañana.

### Flujo sugerido

```
Abrir app ─► GET /me ─► GET /me/departures?date=hoy ─► GET /me/departures/{id}
                                                          │
             (todo se guarda primero en el teléfono)      ▼
        check_in / no_show por pasajero, stage por etapa, fault si algo falla
                                                          │
                                                          ▼
                              POST /sync/events (al tener señal, en lotes)
```

### GET `/me`

Revalida el token al abrir la app y trae los roles y la ficha de personal.

```json
{ "data": {
  "id": 4, "name": "Rubén Canul", "email": "capitan@tortugas.test", "roles": ["capitan"],
  "staff": { "id": "…", "name": "Rubén Canul", "role": "captain", "phone": null,
             "license_number": null, "license_expires_on": "2027-03-16" }
} }
```

`staff` es `null` si el usuario no tiene ficha. `staff.role`: `captain` | `driver` | `guide` | `mechanic`.

### GET `/services`

Catálogo de servicios activos, con precios y etapas. Úsalo para no fijar slugs en el código.

```json
{ "data": [ {
  "id": "…", "slug": "snorkel-3-arrecifes", "name": "Snorkel 3 arrecifes", "category": "snorkel",
  "asset_kind": "boat", "shared": true, "duration_minutes": 150, "capacity": 24, "currency": "MXN",
  "price_adult_cents": 138000, "price_child_cents": 99000, "description": null,
  "stages": [ { "id": "…", "position": 1, "name": "Check-in en muelle", "requires_reading": false } ]
} ] }
```

### GET `/me/departures?date=YYYY-MM-DD`

Mis salidas de un día (fecha local de Cozumel; sin `date`, hoy). Ordenadas por hora. Incluye las canceladas, para que el capitán vea que se canceló.

```json
{ "data": [ {
  "id": "…", "code": "SAL-20261117-A3F9", "status": "scheduled",
  "service": { "id": "…", "slug": "snorkel-3-arrecifes", "name": "Snorkel 3 arrecifes", "shared": true },
  "asset": { "id": "…", "name": "Carey I", "kind": "boat", "registration": "2301-QR", "capacity": 24,
             "reading": { "unit": "hours", "current": 1812 } },
  "starts_at": "2026-11-17T13:30:00+00:00", "ends_at": "2026-11-17T16:00:00+00:00",
  "local_date": "2026-11-17", "local_time": "08:30", "local_end_time": "11:00",
  "departed_at": null, "returned_at": null,
  "my_role": "captain",
  "crew": [ { "name": "Rubén Canul", "role": "captain", "phone": null }, { "name": "Karla Ek", "role": "guide", "phone": null } ],
  "capacity": 24, "bookings_count": 2,
  "pax": { "booked": 5, "checked_in": 0, "no_show": 0 },
  "current_stage": null, "stages_total": 6, "notes": null
} ] }
```

- **`status` de la salida:** `scheduled` | `in_progress` | `completed` | `cancelled`.
- **`asset.reading.unit`:** `hours` (embarcación) o `km` (vehículo). `current` es la última lectura conocida; puede ser `null`.
- **`pax.booked`** no cuenta a los no presentados.

### GET `/me/departures/{id}`

Lo mismo que un elemento de la lista, más `stages` y `manifest`. Si la salida no existe o no eres de su tripulación, responde **404**.

```json
{ "data": {
  "…": "mismos campos que en la lista",
  "stages": [
    { "id": "…", "position": 1, "name": "Check-in en muelle", "requires_reading": false,
      "reached_at": "2026-11-17T13:18:00+00:00", "done": true },
    { "id": "…", "position": 6, "name": "Desembarque y cierre", "requires_reading": true,
      "reached_at": null, "done": false }
  ],
  "manifest": [ {
    "id": "…", "code": "R-7KQ2MX", "status": "confirmed", "adults": 2, "children": 1, "pax": 3,
    "customer": { "name": "Emily Carter", "phone": "+15551234", "country": "US" },
    "cruise_ship": "Icon of the Seas", "all_aboard_at": "2026-11-17T22:30:00+00:00", "all_aboard_local_time": "17:30",
    "pickup": { "id": "…", "name": "Hotel Presidente", "type": "hotel", "address": null,
                "latitude": 20.4675, "longitude": -86.98, "notes": null },
    "checked_in_at": null, "currency": "MXN", "balance_cents": 0, "notes": null
  } ]
} }
```

- **`manifest`:** reservas activas y no presentadas (las canceladas no aparecen), agrupadas por punto de recogida y luego por nombre. `pickup: null` = llega por su cuenta.
- **`balance_cents` > 0:** hay saldo por cobrar en muelle. La app **no** registra pagos; eso lo hace oficina.
- **`all_aboard_local_time`:** resáltalo. Un crucerista que pierde su barco es el peor caso.
- **`reached_at`** de una etapa saltada queda en `null` aunque `done` sea `true`.

### POST `/sync/events`

Todo lo que el capitán o chofer **registra** pasa por aquí: etapas, abordaje, no presentados y fallas. Está pensado para trabajar **sin señal**: guarda cada evento en el teléfono y mándalos en lote cuando haya conexión.

```json
{ "events": [
  { "client_uuid": "1b9d6bcd-bbfd-4b2d-9b5d-ab8dfbbd4bed", "type": "check_in",
    "occurred_at": "2026-11-17T13:15:02Z", "booking_id": "…" },
  { "client_uuid": "6ec0bd7f-11c0-43da-975e-2a8ad9ebae0b", "type": "stage",
    "occurred_at": "2026-11-17T13:34:40Z", "departure_id": "…", "stage_id": "…",
    "latitude": 20.4231, "longitude": -86.9228 },
  { "client_uuid": "…", "type": "stage", "occurred_at": "…", "departure_id": "…", "stage_id": "…", "reading": 1814.5 },
  { "client_uuid": "…", "type": "fault", "occurred_at": "…", "asset_id": "…", "departure_id": "…",
    "severity": "blocking", "description": "La bomba de achique no arranca" }
] }
```

Campos de cada evento:

| Campo | Cuándo | Notas |
|---|---|---|
| `client_uuid` | Siempre | UUID generado en el teléfono **al crear el evento**. No lo cambies al reintentar. |
| `type` | Siempre | `stage` · `check_in` · `check_in_undo` · `no_show` · `fault` |
| `occurred_at` | Siempre | Hora del teléfono en que ocurrió (ISO-8601). Si viene en el futuro, el servidor usa su hora. |
| `departure_id` | `stage` (obligatorio), `fault` (opcional) | |
| `stage_id` | `stage` | `id` de `stages` en el detalle de la salida |
| `reading` | `stage` | Obligatorio si la etapa tiene `requires_reading: true`. Horas de motor (embarcación) o km (vehículo). Opcional en las demás etapas |
| `booking_id` | `check_in`, `check_in_undo`, `no_show` | `id` (UUID) de la reserva en `manifest`, no el `code` |
| `asset_id` | `fault` | `asset.id` de la salida |
| `severity` | `fault` | `minor` (puede operar) · `blocking` (no debe salir) |
| `description` | `fault` | Hasta 2000 caracteres |
| `latitude`, `longitude` | Opcional en todos | Posición del teléfono; se guarda con el evento |

Máximo **200 eventos** por petición. Si el lote está mal formado (falta un campo, UUID inválido, `type` desconocido), responde **422** y no aplica nada: es un error de la app, corrígelo antes de reintentar.

**Respuesta 200:** un resultado por evento, en el mismo orden en que se mandaron.

```json
{ "results": [
  { "client_uuid": "1b9d…", "status": "applied" },
  { "client_uuid": "6ec0…", "status": "ignored", "message": "Ya se había registrado esta etapa o una posterior." },
  { "client_uuid": "…", "status": "rejected", "reason": "reading_required", "message": "Captura el horómetro para registrar esta etapa." },
  { "client_uuid": "…", "status": "duplicate" }
] }
```

| `status` | Qué significa | Qué hace la app |
|---|---|---|
| `applied` | Se aplicó | Borrarlo de la cola |
| `ignored` | Se guardó, pero no cambió nada (ya estaba hecho o llegó tarde) | Borrarlo de la cola |
| `duplicate` | Ya se había recibido ese `client_uuid` | Borrarlo de la cola |
| `rejected` | No se puede aplicar y reenviarlo igual no cambiará el resultado | Borrarlo de la cola y mostrar `message` |

Si la petición falla por red, 5xx o 401, **no borres nada** y reintenta el mismo lote más tarde: gracias a `client_uuid`, lo que ya había entrado regresa como `duplicate`.

**`reason` de un rechazo:**

| `reason` | Cuándo |
|---|---|
| `not_found` | La salida, etapa, reserva o unidad no existe (o la etapa no es de ese servicio) |
| `not_crew` | No estás en la tripulación de esa salida |
| `departure_cancelled` | La salida se canceló |
| `departure_closed` | Deshacer un abordaje cuando la salida ya terminó |
| `booking_cancelled` | La reserva se canceló |
| `checked_in` | `no_show` a alguien que ya está registrado. Manda antes `check_in_undo` |
| `reading_required` | La etapa pide lectura y no viene `reading` |
| `invalid_reading` | La lectura es menor que la última registrada de esa unidad (probable error de captura) |

Ante `reading_required` o `invalid_reading`, pide la lectura otra vez y manda un evento **nuevo** (con otro `client_uuid`).

#### Reglas de cada tipo

- **`stage`:** las etapas **solo avanzan**. Puedes saltar etapas (de la 2 a la 4). Si llega una etapa igual o anterior a la actual, se responde `ignored`.
  - La primera etapa que se registra pone la salida en `in_progress` y fija `departed_at`.
  - La **última** etapa la pone en `completed`, fija `returned_at` y marca como `completed` las reservas que abordaron. Las que no abordaron se quedan como están para que oficina las revise.
  - `reading` actualiza el horómetro u odómetro de la unidad.
- **`check_in`:** el pasajero abordó (capitán) o subió al vehículo (chofer). Si estaba como no presentado, regresa a `confirmed` (si tiene pagos) o `pending`. Repetirlo responde `ignored`.
- **`check_in_undo`:** quita el registro, por si se marcó por error. Si hay un `check_in` y un `check_in_undo` de la misma reserva desde dos teléfonos, gana el de `occurred_at` más reciente, sin importar cuál llegue primero.
- **`no_show`:** la reserva pasa a `no_show` y libera sus lugares.
- **`fault`:** crea un reporte de falla que coordinación ve en la ficha de la unidad. Si es `blocking`, además aparece en las alertas del tablero. **No** saca la unidad de servicio ni cancela salidas; eso lo decide coordinación.

#### Orden y conflictos

- El servidor aplica cada lote en orden de `occurred_at`, no en el orden en que llega.
- Capitán y guía pueden registrar la misma salida desde dos teléfonos. Cada evento bloquea la salida mientras se aplica, así que no se pisan.
- Después de sincronizar, vuelve a pedir `GET /me/departures/{id}` para mostrar el estado real.

### Recomendaciones para la app de campo

- **Cola local:** guarda cada evento con su `client_uuid` en SQLite (`@capacitor-community/sqlite`) o en Preferences, no solo en memoria. Si la app se cierra en el mar, los eventos no se pierden.
- **Cuándo sincronizar:** al recuperar conexión (`@capacitor/network`), al volver a primer plano y cada 1 o 2 minutos mientras haya eventos pendientes.
- **Interfaz optimista:** muestra el cambio al instante (etapa avanzada, pasajero palomeado) con un indicador de "pendiente de enviar" hasta que llegue `applied`.
- **Caché de lectura:** guarda la última respuesta de `/me/departures` y del detalle para poder abrir el manifiesto sin señal.
- **Lectura:** cuando `requires_reading` sea `true`, pide la lectura antes de guardar el evento y muestra `asset.reading.current` como referencia.

## 8. Pendiente en el backend (aún no existe)

- **Corridas de vehículo y recogidas** (fase 3): hoy el chofer ve las recogidas de las salidas en las que va como tripulación, sin hora de recogida ni orden de paradas. Las corridas de traslado (van que lleva a varios grupos al muelle) todavía no existen.
- **Posiciones GPS continuas y mapa** (fase 3): hoy solo se guarda la posición que venga con cada evento.
- **Tiempo real (Reverb):** el panel todavía no recibe los eventos de la app al instante; hay que refrescar la pantalla.
- **Cobro en muelle desde la app:** el manifiesto muestra el saldo, pero registrar el pago sigue siendo de oficina.
- **Notificaciones push** cuando cambie una salida asignada.
