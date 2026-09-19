# QA y pruebas

## Estrategia

| Suite | Qué prueba | Base de datos | Tiempo aprox. |
|---|---|---|---|
| **Unit** (24) | Lista blanca del executor, mapeo de resultados, PBKDF2 legacy, hora local, mensajes | Ninguna (mock que falla si se toca la conexión) | < 1 s |
| **Feature** (17) | HTTP completo: login, cabeceras, 403, validación, reserva, errores de negocio → UX/JSON | Ninguna: repositorios **mockeados** por interfaz | ~1 s |
| **Integration** (15) | Reglas reales en SQL Server: concurrencia, idempotencia, versión, permisos, reprogramación, solapes, bloqueos, inactivos, plazo, seed | `ReservasMedicasWeb_Test` | ~7 s |
| **VALIDATE.sql** (187) | Objetos, índices, permisos del rol, reglas y seeds del esquema | BD objetivo | ~2 s |

Las pruebas de integración se **omiten** (no fallan) si la BD configurada no termina en `_Test` o no está disponible; así
nunca pueden escribir en la BD de desarrollo. El worker de concurrencia repite la comprobación antes de conectarse.

## Cómo ejecutar

```powershell
.\scripts\test.ps1 -PrepareTestDatabase -Lint   # crea/actualiza ReservasMedicasWeb_Test (no destructivo) + Pint + todas
.\scripts\test.ps1 -Suite Unit
.\scripts\test-concurrency.ps1 -Repeat 5        # carrera real repetida
.\scripts\db-validate.ps1                       # 187 comprobaciones sobre ReservasMedicasWeb
php artisan test                                # equivalente directo
```

## Casos

### Unit
- `StoredProcedureExecutorTest`: rechaza `clin.`/`seg.`, sin esquema, inyección `; DROP`, comentarios, tablas, `sp_executesql`
  (select y command), vistas fuera de `api`, `ORDER BY` inseguro, columna de filtro insegura — **sin abrir conexión**.
- `ProcedureResultTest`: fila estándar → DTO; `Exito = 0` → `BusinessRuleException` con `businessCode`; paginación con `TotalFilas`.
- `SecurityHelpersTest`: ida y vuelta PBKDF2-SHA256; UTC → hora de Lima; fallback de mensajes; etiqueta para cada rol.

### Feature
- `AccessControlTest`: invitado → login; cabeceras de seguridad y `X-Correlation-ID`; correlation id malformado no se refleja;
  paciente recibe 403 en `/admin/usuarios`, `/auditoria` y `POST /admin/usuarios`; admin ve el listado.
- `BookingFlowTest`: payload inválido no llega al repositorio; **paciente con `patient_id` manipulado reserva para sí**;
  recepción reserva para el paciente elegido; `SLOT_OCUPADO` vuelve al formulario con mensaje UX; `CONFLICTO_EDICION` como
  JSON 422; auditor recibe 403.
- `LoginTest`: login correcto; clave errónea y usuario inexistente con el **mismo** mensaje genérico y registro del fallo;
  cuenta inactiva; hash legacy re-hasheado a bcrypt; la página de login no muestra contraseñas demo.

### Integration (SQL Server real)
- `ReservationConcurrencyTest`: 6 procesos PHP independientes (cada uno con su conexión) se sincronizan con una barrera de
  reloj e intentan reservar el **mismo** slot → exactamente 1 éxito; los demás `SLOT_OCUPADO` o `CONCURRENCIA`. La reserva
  ganadora se cancela al final (prueba repetible).
- `ReservationRulesTest`:
  - misma `IdempotencyKey` → `RESERVA_EXISTENTE` con el mismo id; misma clave con otro slot → `IDEMPOTENCIA_CONFLICTO`;
  - reprogramación del paciente: original REPROGRAMADA con enlace a la nueva, nueva CONFIRMADA en el slot destino, el slot
    original queda libre y lo toma otra persona; una REPROGRAMADA no se puede reprogramar otra vez;
  - cancelar con versión vieja → `CONFLICTO_EDICION`;
  - SQL niega: paciente reservando para otro, auditor reservando (`SIN_PERMISO`);
  - un paciente no puede leer la reserva de otro;
  - `clinic:seed-demo` es idempotente (segunda ejecución no crea nada).
- `CriticalValidationsTest`: usa médicos de prueba propios (`IT-FIX-1..3`) con agendas nocturnas 21:00–22:00 (fuera del
  horario demo) y restaura todo al terminar (cancela reservas, desactiva agendas, reactiva al médico, restaura la
  configuración). Verificado repetible: dos corridas seguidas pasan y no dejan agendas ni reservas activas.

## Cobertura de las validaciones críticas (§116)

| # | Validación | Código SQL esperado | Prueba |
|---|---|---|---|
| 1 | Dos requests al mismo slot | 1 éxito; resto `SLOT_OCUPADO`/`CONCURRENCIA` | `ReservationConcurrencyTest` (6 procesos) |
| 2 | Paciente con citas solapadas | `PACIENTE_CITA_SOLAPADA` | `CriticalValidationsTest` |
| 3 | Agenda del médico solapada | `MEDICO_AGENDA_SOLAPADA` | `CriticalValidationsTest` |
| 4 | Consultorio solapado | `CONSULTORIO_SOLAPADO` | `CriticalValidationsTest` |
| 5 | Slot bloqueado | `SLOT_BLOQUEADO` | `CriticalValidationsTest` |
| 6 | Agenda inactiva | `AGENDA_INACTIVA` | `CriticalValidationsTest` |
| 7 | Paciente inactivo | `PACIENTE_INACTIVO` | `CriticalValidationsTest` |
| 8 | Médico inactivo | `MEDICO_INACTIVO` | `CriticalValidationsTest` |
| 9 | Reprogramar a slot ocupado | `SLOT_OCUPADO` (la original sigue CONFIRMADA) | `CriticalValidationsTest` |
| 10 | Cancelación fuera de política | `FUERA_DE_PLAZO` (paciente); el personal sí puede | `CriticalValidationsTest` |
| 11 | Actor sin permiso | `SIN_PERMISO` | `ReservationRulesTest` + 403 en `AccessControlTest` |
| 12 | Clave de idempotencia repetida | `RESERVA_EXISTENTE` / `IDEMPOTENCIA_CONFLICTO` | `ReservationRulesTest` |
| 13 | Conflicto de rowversion | `CONFLICTO_EDICION` | `ReservationRulesTest` |

## Resultados (última ejecución, 2026-09-18)

```
php artisan test
Tests: 56 passed (249 assertions)
  Unit 24 passed (43) · Feature 17 passed (70) · Integration 15 passed (136)
vendor/bin/pint --test      → OK
ReservasMedicasWeb_VALIDATE.sql → VALIDACION: OK 187/187
npm run build               → OK (Vite, 67 módulos)
```


## Pruebas manuales (smoke HTTP)

Realizadas contra `php artisan serve` y la BD de desarrollo:

| Caso | Resultado |
|---|---|
| Login de cada rol y acceso a su panel | OK |
| Paciente reserva por el asistente | OK |
| Doble envío con la misma clave | Misma reserva, sin duplicado |
| Cancelación y reintento con versión vieja | OK / `CONFLICTO_EDICION` |
| POST sin token CSRF | 419 |
| Paciente reprograma por la UI (`NX-00000001` → `NX-00000046`) | OK; reintento sin duplicado; bitácora `RESERVA_REPROGRAMADA` |

## Pendiente de verificación manual

- **Responsive**: el marcado usa la grilla de Bootstrap (`col-sm/md/lg/xl`), `table-responsive`, `offcanvas` para el menú y
  `meta viewport` en ambos layouts, pero **no se verificó visualmente** en un navegador a 375–414 px en esta construcción
  (el navegador automatizado no alcanzaba el servidor local). Revisar: login, asistente de reserva, mis citas, agenda del día.
- Prueba en navegadores distintos de Chrome.
