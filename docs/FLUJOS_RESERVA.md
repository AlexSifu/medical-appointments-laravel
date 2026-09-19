# Flujos de reserva

## Estados

```
                 ┌──────────── CANCELADA (2)     libera el horario
                 │
CONFIRMADA (1) ──┼──────────── REPROGRAMADA (3)  libera el horario; apunta a la nueva reserva (CONFIRMADA)
                 │
                 ├──────────── ATENDIDA (4)      ocupa (histórico)
                 └──────────── NO_ASISTIO (5)    ocupa (histórico)
```

Solo CONFIRMADA admite transiciones (`clin.TransicionesEstadoReserva`); los demás estados son finales. Cada transición
queda en `clin.ReservaHistorial` y en `audit.BitacoraSistema`.

## 1. Reservar (paciente o recepción)

Asistente de 5 pasos (`/reservar`, `booking.js`): especialidad → médico y sede → fecha → horario → confirmar.

1. Las fechas vienen de `api.usp_AgendaFechasDisponibles` y los horarios de `api.usp_AgendaHorariosEstado`
   (disponible / ocupado / bloqueado / pasado), respetando `MINUTOS_MINIMOS_ANTICIPACION` y `MAX_DIAS_RESERVA_FUTURA`.
2. El formulario lleva un `idempotency_key` (UUID) generado al cargar la página.
3. `POST /reservas` → `ReservationRequest` → `ReservationService::book()`:
   - paciente: `api.usp_ReservaCrear` con `@PacienteId = NULL` (SQL resuelve la ficha del usuario; un `patient_id` enviado se ignora);
   - recepción/admin: `api.usp_RecepcionReservaCrear` con el paciente elegido.
4. En SQL (`clin.usp_ReservaTomarLocks` + `clin.usp_ReservaValidarEInsertar`, transacción `SERIALIZABLE`):

| Orden | Validación | Código si falla |
|---|---|---|
| 1 | Permiso y pertenencia | `SIN_PERMISO`, `PACIENTE_REQUERIDO` |
| 2 | Clave de idempotencia ya usada: mismos datos → devuelve la existente | `RESERVA_EXISTENTE` (éxito) / `IDEMPOTENCIA_CONFLICTO` |
| 3 | Locks `UPDLOCK, HOLDLOCK` sobre el slot y luego el paciente (orden fijo, evita deadlocks) | — |
| 4 | Slot existe, agenda activa, slot no bloqueado | `SLOT_NO_EXISTE`, `AGENDA_INACTIVA`, `SLOT_BLOQUEADO` |
| 5 | Médico y paciente activos | `MEDICO_INACTIVO`, `PACIENTE_INACTIVO` |
| 6 | No es pasado; anticipación mínima; dentro del horizonte | `FECHA_PASADA`, `ANTICIPACION_INSUFICIENTE`, `FUERA_DE_RANGO` |
| 7 | Slot libre | `SLOT_OCUPADO` |
| 8 | Paciente sin otra cita solapada; médico y consultorio sin cruce | `PACIENTE_CITA_SOLAPADA`, `MEDICO_CRUCE`, `CONSULTORIO_CRUCE` |
| 9 | Máximo de reservas activas por paciente | `LIMITE_RESERVAS` |
| 10 | `INSERT` + historial + bitácora | `RESERVA_CREADA` |

Si dos peticiones pasan la validación a la vez, el índice único filtrado `UX_Reservas_HorarioOcupado` rechaza la segunda
(se traduce a `SLOT_OCUPADO`). Deadlock o lock timeout → `CONCURRENCIA` con mensaje "inténtalo de nuevo".

5. Éxito → redirect a `/reservas/{id}?confirmada=1` con el código `NX-00000001`.
   Fallo → vuelve al asistente con el mensaje de negocio (p. ej. "Ese horario acaba de ser tomado. Elige otro.").

## 2. Cancelar

`POST /reservas/{id}/cancelar` con `reason_id`, `notes` y `version` (rowversion leída al mostrar la cita) →
`api.usp_ReservaCancelar`:
- permiso `reservas.cancelar` + pertenencia; paciente: plazo `HORAS_MINIMAS_CANCELACION` y sin motivos "solo personal";
- motivo `OTRO` exige observación;
- versión distinta a la actual → `CONFLICTO_EDICION` ("La cita fue modificada por otra persona. Recarga…");
- estado ≠ CONFIRMADA → `ESTADO_INVALIDO`;
- éxito: estado CANCELADA, `OcupaHorario = 0` (el slot vuelve a estar disponible), historial y bitácora.

## 3. Reprogramar

`GET /reservas/{id}/reprogramar` reutiliza el asistente (modo `reschedule`, con especialidad fija). `POST` →
`api.usp_ReservaReprogramar(@ReservaId, @NuevoHorarioMedicoId, @Motivo, @VersionFila, @IdempotencyKey)` en **una** transacción:

1. valida permiso, pertenencia, versión (`CONFLICTO_EDICION`), estado CONFIRMADA (`ESTADO_INVALIDO`), que la cita no haya
   empezado (`CITA_PASADA`) y, para pacientes, el plazo `HORAS_MINIMAS_CANCELACION` (`FUERA_DE_PLAZO`);
2. ejecuta `clin.usp_ReservaValidarEInsertar` para el slot nuevo con las mismas validaciones y locks que una reserva nueva,
   **excluyendo la reserva original** del control de solapamiento del paciente, y crea la nueva CONFIRMADA con `ReservaOrigenId`;
3. marca la original REPROGRAMADA (`OcupaHorario = 0`, libera su slot) con `ReservaReemplazoId`, historial en ambas y bitácora
   `RESERVA_REPROGRAMADA`;
4. todo en la misma transacción: si algo falla, se revierte y la original sigue CONFIRMADA.

Reintento con la misma clave → devuelve la nueva reserva sin duplicar. Una reserva REPROGRAMADA no puede volver a reprogramarse.

## 4. Cerrar la cita (médico)

`/medico/hoy` lista las citas del día. `POST /reservas/{id}/atendida` o `/no-asistio` → `api.usp_MedicoMarcarAtendida` /
`api.usp_MedicoMarcarNoAsistio`: solo el médico tratante (o personal autorizado), solo a partir de la hora de inicio, con
control de versión. Ambos estados siguen ocupando el horario (histórico).

## 5. Bloqueos y agendas

- Crear agenda (día o rango ≤ 92 días) genera los slots; se rechazan cruces de médico o consultorio.
- Bloquear un rango se rechaza si hay citas confirmadas en el intervalo (`RESERVAS_EN_INTERVALO`): primero se reprograman o cancelan.
- Desactivar una agenda solo es posible si no tiene reservas confirmadas (`RESERVAS_EN_AGENDA`).

## Verificación

| Flujo | Evidencia |
|---|---|
| Reserva de paciente, reintento idempotente, cancelación, versión vieja, CSRF | Smoke HTTP de la sesión de construcción (ver bitácora) |
| Reprogramación por UI | Smoke HTTP: reserva `NX-00000001` → `NX-00000046`, doble envío sin duplicado, bitácora `RESERVA_REPROGRAMADA` |
| Concurrencia | `tests/Integration/ReservationConcurrencyTest.php`: 6 procesos PHP, exactamente 1 ganador |
| Idempotencia, conflicto de versión, permisos SQL, reprogramación | `tests/Integration/ReservationRulesTest.php` |
| Solapes (paciente, médico, consultorio), slot bloqueado, agenda/paciente/médico inactivo, reprogramar a slot ocupado, cancelación fuera de plazo | `tests/Integration/CriticalValidationsTest.php` |
| Mensajes UX y paciente no puede reservar para otro | `tests/Feature/BookingFlowTest.php` |
