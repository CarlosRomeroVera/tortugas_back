# Avance del proyecto · Tortugas Ops

Documento vivo: estado por fase, perfiles y permisos, y una bitácora que se llena cada día que hay avance.
Plan de referencia: propuesta "Tortugas Ops en Laravel + Filament" (sección *05 · Plan*).

**Última actualización:** 2026-11-23 · rama `fase-1` · 87 pruebas pasando

---

## 1. Estado por fase

Leyenda: ✅ hecho · 🟡 parcial · ⏳ pendiente

### Fase 1 · Catálogos y reservas — ✅ terminada

| Entregable | Estado | Dónde |
|---|---|---|
| Recursos (embarcaciones y vehículos), servicios, etapas, horarios, hoteles y muelles | ✅ | `app/Filament/Resources/{Assets,Boats,Vehicles,Services,Locations}` |
| Ocupaciones con bloqueo de fila y prueba de concurrencia | ✅ | `app/Actions/Allocations/AllocateAsset.php`, `tests/Feature/Actions/AllocateAssetTest.php` |
| Reservas, pagos (MXN/USD) y disponibilidad | ✅ | `app/Actions/{Bookings,Payments}`, `app/Services/Availability.php`, API `/api/v1` |
| Qué embarcaciones pueden dar cada servicio | ✅ | tabla `asset_service`, `Service` |
| Generación de salidas compartidas desde horarios fijos | ✅ | `php artisan departures:generate` |
| Roles y auditoría | 🟡 | Roles sí; auditoría con activitylog en reservas, pagos, ocupaciones, mantenimiento y fallas. Falta permisos por pantalla (ver sección 2) |

### Fase 2 · Operación en vivo — 🟡 casi lista (falta Reverb)

| Entregable | Estado | Dónde / nota |
|---|---|---|
| Salidas y tripulación | ✅ | `app/Actions/Crew/AssignCrew.php`, relation manager de tripulación en Salidas |
| API de sincronización offline (eventos de campo, etapas, abordaje, lecturas, fallas) | ✅ | `POST /api/v1/sync/events`, `app/Actions/Field/RecordFieldEvent.php`, guía en `docs/api-app-movil.md` §7 |
| Reportes de falla desde la app | ✅ | `FaultReport`, relation manager en Recursos |
| Tablero en vivo | 🟡 | Widgets `FleetTimeline`, `OpsStats`, `OpsAlerts` con consulta cada 30 s |
| Tiempo real con Reverb | ⏳ | No instalado todavía |
| App móvil en Ionic | 🟡 | Construida en `../tortugas_app` (Ionic Vue): oficina, capitán y chofer contra la API real, con cola sin señal. Falta recorrerla completa con la guía de pruebas |

### Fase 3 · Transporte — ⏳ sin empezar

| Entregable | Estado | Nota |
|---|---|---|
| Recogidas y corridas de vehículo | ⏳ | Las reservas ya guardan `pickup_location_id`; faltan corridas, hora y orden de paradas |
| App del chofer | ⏳ | Hoy el chofer solo ve las salidas donde va como tripulación |
| Posiciones GPS y mapa | ⏳ | Solo se guarda la posición que trae cada evento |
| Alertas de crucero | ⏳ | Las reservas ya guardan `cruise_ship` |

### Fase 4 · Mantenimiento y reportes — 🟡 adelantada en parte

| Entregable | Estado | Nota |
|---|---|---|
| Órdenes de mantenimiento con bloqueo de agenda | ✅ | `app/Actions/Maintenance`, recurso MaintenanceOrders |
| Preventivo automático por horas de motor | ⏳ | Falta el comando programado |
| Cierres de puerto (acción masiva) | ⏳ | Depende de respuesta del cliente |
| Tablas resumen para reportes | ⏳ | |
| Integración con el sitio web | ⏳ | Depende de respuesta del cliente |

---

## 2. Perfiles y permisos

Los perfiles son **roles** de Spatie Permission (`database/seeders/RoleSeeder.php`). Todavía **no hay permisos por acción**: el rol solo decide si entra al panel y qué rutas de la API puede usar.

| Rol | Panel `/admin` | API `/api/v1` | Usuario de prueba |
|---|---|---|---|
| `admin` | Sí, acceso completo | Catálogo, disponibilidad, reservas, pagos, cancelaciones | `admin@tortugas.test` |
| `coordinacion` | Sí, acceso completo | Igual que admin | `coordinacion@tortugas.test` |
| `ventas` | Sí, acceso completo | Igual que admin | `ventas@tortugas.test` |
| `mantenimiento` | Sí, acceso completo | Solo `me`, `services`, `availability` | — (no hay usuario de prueba) |
| `capitan` | No | `me`, `services`, `availability`, `me/departures`, `sync/events` | `capitan@tortugas.test` |
| `chofer` | No | Igual que capitán | `chofer@tortugas.test` |

Contraseña de los usuarios de prueba: `password`.

**Dónde se controla:**
- Acceso al panel: `User::OFFICE_ROLES` y `canAccessPanel()` en `app/Models/User.php`.
- API de oficina (reservas y pagos): `hasAnyRole(['admin','coordinacion','ventas'])` en `StoreBookingRequest`, `BookingController` y `PaymentController`.
- API de campo: middleware `role:capitan|chofer` en `routes/api.php`.

**Puesto del personal (distinto al rol):** `StaffRole` = `captain`, `driver`, `guide`, `mechanic`. Es la ficha de personal que se asigna como tripulación; no da permisos.

**Pendiente de definir con el cliente:** qué puede ver y editar cada perfil de oficina. Hoy `ventas` y `mantenimiento` pueden editar lo mismo que `admin` en el panel.

---

## 3. Pendientes abiertos

- [ ] Instalar Reverb y transmitir eventos de campo al tablero (cierra Fase 2).
- [ ] Permisos por pantalla en Filament (policies) según matriz que apruebe el cliente.
- [ ] Usuario de prueba con rol `mantenimiento`.
- [ ] Cobro en muelle desde la app (hoy solo oficina).
- [ ] Notificaciones push cuando cambia una salida asignada.
- [ ] Ajustar el precio de los privados según la respuesta del cliente (pregunta 8).

### Preguntas para el cliente

1. ¿El sitio web ya usa un sistema de reservas o un procesador de pago?
2. ¿Cuántas embarcaciones, vehículos y personas tienen en total?
3. ¿Las salidas de snorkel son compartidas con horarios fijos? ¿Los privados tienen horario libre?
4. ¿Cobran en MXN y USD? ¿Qué tipo de cambio usan y quién lo actualiza?
5. ¿Trabajan con agencias a crédito o con comisión?
6. ¿Qué hacen hoy cuando Capitanía cierra el puerto?
7. ¿Qué puede ver y hacer cada perfil de oficina (ventas, coordinación, mantenimiento)?
8. ¿Cómo cobran los tours privados: precio fijo por embarcación, por persona o por persona con un mínimo?
   Hoy el sistema cobra todo por persona (`Service::priceFor`), así que un privado de 2 personas cuesta la cuarta parte que uno de 8 aunque ambos ocupen la embarcación completa.

---

## 4. Bitácora

Una entrada por día con avance, la más reciente arriba. Formato: qué se hizo, decisiones tomadas y qué sigue.

### 2026-11-23
- Guía de pruebas paso a paso para preparar la demo al cliente: [Guía de pruebas — Web y App Tortugas](https://claude.ai/artifact/LJZYARKoziKCbg2MrqqFkH). Cubre preparación del entorno, panel web por módulo (W1–W7), app móvil (A0–A8), 16 variantes y casos límite, guion de 30 min y checklist del día.
- Se corrigió el estado de la app: ya existe en `../tortugas_app` con pantallas de oficina (disponibilidad, reservas, pagos, cancelación), capitán y chofer (mis salidas, pase de lista, etapas, lectura, fallas) y modo sin señal.
- Recordatorio para la demo: el seeder crea salidas desde el día siguiente; correr `migrate:fresh --seed` la víspera o esa mañana.
- 87 pruebas del backend pasando.
- **Sigue:** ensayar la guía completa en web y app, y después Reverb para el tablero en vivo.

### 2026-11-19
- Se revisó el avance contra el plan y se creó este documento.
- Estado: Fase 1 terminada, Fase 2 a falta de Reverb y la app Ionic.
- Panel: los botones turquesa ahora llevan texto blanco. La paleta primaria se recorrió un tono más oscuro (`AdminPanelProvider::primaryPalette()`), porque Filament solo usa texto blanco si el contraste es ≥ 4.5:1.
- Hallazgo: los privados ocupan la embarcación completa, pero se cobran por persona. Se agregó como pregunta 8 para el cliente.
- **Sigue:** Reverb para el tablero en vivo.

### 2026-11-16
- API de operación de campo para capitán y chofer: `me`, `services`, `me/departures`, `sync/events` (offline, idempotente). Tripulación por salida y reportes de falla.
- Elegibilidad de recursos por servicio (`asset_service`): reservas y salidas solo usan embarcaciones habilitadas para ese servicio.
- Guía de la API para el equipo de la app: `docs/api-app-movil.md`.

### 2026-11-15
- Arranque del proyecto: Laravel 12 + Filament 4 + MySQL 8 + Sanctum.
- Catálogos, recursos, ocupaciones con bloqueo de fila (no traslape garantizado en código), reservas, pagos, disponibilidad, mantenimiento.
- Panel con widgets de operación (agenda de flota, estadísticas, alertas) refrescando cada 30 s.
- Decisión: PostgreSQL → MySQL 8; el no traslape lo garantiza `AllocateAsset` con `lockForUpdate`.
