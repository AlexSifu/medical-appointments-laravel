# Arquitectura Laravel

## Visión general

```
Navegador (Blade + Bootstrap 5.3 + JS Vite)
   │  HTML (formularios con CSRF) / fetch GET JSON
   ▼
Middleware: AssignCorrelationId → StartSession/CSRF → auth → refresh.user → permission:<códigos> → SecurityHeaders
   ▼
Controller (delgado: sin reglas de negocio)
   ▼
FormRequest (formato, tipos, longitudes; nunca reglas de disponibilidad)
   ▼
Service (orquesta casos de uso: ReservationService, ScheduleService, AdminService, …)
   ▼
Repository (interfaz en Contracts/, implementación en SqlServer/; nombres de SP como constantes privadas)
   ▼
StoredProcedureExecutor (lista blanca: solo api.usp_* / api.vw_*, parámetros enlazados, contexto de auditoría)
   ▼
SQL Server — esquema api (superficie pública) → seg / clin / audit (internos, sin permisos para la app)
```

**SQL Server es la autoridad.** Cada regla de negocio (permisos, pertenencia, disponibilidad, solapamientos, límites, plazos,
estados, idempotencia, auditoría) vive en los procedimientos. Laravel repite algunas validaciones de *formato* solo para dar
mejor UX; si se omiten, el SP igual rechaza. Un cliente malicioso que llame al SP directamente obtiene el mismo resultado.

## Carpetas

| Carpeta | Contenido |
|---|---|
| `app/Http/Controllers` | Por área: `Auth`, `Admin`, `Reception`, `Doctor`, `Patient`, `Auditor` + `BookingController`, `ReservationController`, `DashboardController` |
| `app/Http/Requests` | FormRequests (base `NexaFormRequest`: `actor()`, regla de versión `0x` + 16 hex) |
| `app/Http/Middleware` | `AssignCorrelationId`, `EnsurePermission`, `RefreshAuthenticatedUser`, `SecurityHeaders` |
| `app/Services` | 11 servicios de caso de uso + `DemoSeedService` |
| `app/Repositories/Contracts` | Interfaces (permiten mockear SQL en pruebas Feature) |
| `app/Repositories/SqlServer` | Implementaciones: una constante por SP consumido |
| `app/DTO` | DTO inmutables creados desde filas (`fromRow`), `ProcedureResult`, `PagedResult` |
| `app/Models` | **Modelos de dominio documentales, no Eloquent**: describen tabla, relaciones y SP que la persisten; no escriben |
| `app/Policies` | `ReservationPolicy`: solo decide qué botones mostrar (el SP vuelve a decidir) |
| `app/Support` | `StoredProcedureExecutor`, `RequestContext`, guard de sesión, hasher PBKDF2 legacy, `LocalTime`, `ErrorMessages`, `CsvExport` |
| `resources/views` | Blade con 16 componentes (`x-card`, `x-table`, `x-time-slot`, `x-modal`, …) y vistas por rol |
| `resources/js` | `app.js` (modales, confirmaciones), `booking.js` (asistente), `schedule.js`, `charts.js` |
| `database/sql` | MASTER, VALIDATE, MIGRATE_FROM_V1 (solo lectura), RESET_DEV (destructivo, protegido) |

## ¿Por qué no Eloquent ni migraciones?

- El esquema tiene reglas que Eloquent no expresa (índices únicos filtrados, `CHECK` de coherencia de estado, `rowversion`,
  `SERIALIZABLE` + `UPDLOCK/HOLDLOCK`). Mantener un único script MASTER idempotente evita dos fuentes de verdad.
- Escribir con Eloquent permitiría saltarse las reglas. La cuenta de la app **no tiene permisos** sobre `seg`/`clin`/`audit`:
  un `DB::table('clin.Reservas')->insert()` fallaría por permisos incluso si alguien lo escribiera.
- Por eso `app/Models` contiene clases de dominio que documentan la tabla y los SP asociados (requisito "modelos" de la prueba),
  y los datos viajan como DTO.

## StoredProcedureExecutor

```php
$this->sql->command('api.usp_ReservaCancelar', [
    'ActorUsuarioId' => $actorId, 'ReservaId' => $id, 'MotivoCancelacionId' => $reasonId,
    'Observacion' => $notes, 'VersionFila' => $version,
]);   // añade @Ip, @UserAgent, @CorrelationId desde RequestContext
```

- Nombre validado con `/^api\.usp_[A-Za-z0-9]+$/` (vistas: `/^api\.vw_[A-Za-z0-9]+$/`). Cualquier otro → excepción, sin tocar la BD.
- Parámetros siempre enlazados (`EXEC name @P = ?`), nunca concatenados.
- `view()` acepta filtros de igualdad cuyo nombre de columna se valida y un `ORDER BY` de lista blanca.
- Errores SQL: 50409 (conflicto de concurrencia lanzado por SP), 1205 (deadlock), 1222 (lock timeout) → `ConcurrencyException`; 50403 → `PermissionDeniedException`; conexión → `DatabaseUnavailableException`.

## Resultado estándar de comandos

Todo SP de escritura devuelve una fila `Exito | Codigo | Mensaje | EntidadId`. `ProcedureResult::throwIfFailed()` convierte
`Exito = 0` en `BusinessRuleException(businessCode)`. `bootstrap/app.php` la renderiza como redirect con alerta
(o JSON 422 `{code, message}` para fetch). `ErrorMessages` traduce códigos a mensajes de UX sin detalles técnicos.

## Autenticación

Guard propio `SessionUserGuard` (driver `nexa-session`): guarda en sesión el `UsuarioId` y un snapshot de roles/permisos
(`AuthenticatedUserData`). `RefreshAuthenticatedUser` lo relee cada 300 s; si la cuenta fue desactivada, cierra la sesión.
Las contraseñas se verifican en PHP (bcrypt, o PBKDF2 legacy con rehash a bcrypt), pero bloqueos e intentos los decide SQL.

## Hora

Las fechas de cita son hora civil de la clínica (`America/Lima`, calculada en SQL con `clin.fn_AhoraLocal()`); la auditoría
se guarda en UTC y `LocalTime::fromUtc()` la muestra en hora local.

## Trazabilidad

Cada petición recibe un `X-Correlation-ID` (se acepta el entrante solo si es un UUID válido). Viaja a cada SP y queda en
`audit.BitacoraSistema`, de modo que una línea de log de Laravel se une con su registro de auditoría.
