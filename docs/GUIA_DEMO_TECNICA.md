# Guía de demo técnica (10–15 min)

**Antes de empezar**
- `.\scripts\check-environment.ps1` en verde y `.\scripts\start.ps1` corriendo (o `php artisan serve`).
- Las cuentas demo son `superadmin`, `administrador`, `recepcion`, `medico`, `paciente` y `auditor` (usuarios en `.env`).
  Sus contraseñas se definen **solo** en `.env` (`DEMO_*_PASSWORD`) y no se muestran en pantalla ni en esta guía.
- Ten a mano una terminal con `sqlcmd -S localhost -E -d ReservasMedicasWeb` y otra en la raíz del proyecto.
- Usa dos navegadores (o una ventana privada) para tener dos sesiones a la vez.

| # | Tema | Min | Qué mostrar | Mensaje clave |
|---|---|---|---|---|
| 1 | Arquitectura | 1.5 | Diagrama de `docs/ARQUITECTURA_LARAVEL.md`; abrir `BookingController@store` → `ReservationService` → `SqlServerReservationRepository` (constante `api.usp_ReservaCrear`) | Laravel orquesta; SQL Server decide. Sin Eloquent, los nombres de SP son constantes con lista blanca `api.` |
| 2 | Login | 1 | `/login` con clave errónea (mensaje genérico); login correcto; cabecera `X-Correlation-ID` en DevTools | Mismo mensaje para usuario inexistente o clave errónea; bloqueo tras 5 intentos lo decide SQL |
| 3 | Reserva del paciente | 2 | `paciente` → `/reservar`: especialidad → médico/sede → fecha → horario → confirmar. Mostrar código `NX-…` y la cita en `/mis-citas` | Solo aparecen fechas/horarios reservables (`fn_HorariosReservables`); el paciente siempre reserva para sí |
| 4 | Concurrencia | 1.5 | `.\scripts\test-concurrency.ps1`: 6 procesos al mismo slot → 1 éxito. En la UI: dos sesiones eligen el mismo horario, confirman; la segunda recibe "El horario acaba de ser reservado…" | Índice único filtrado + `SERIALIZABLE` + `sp_getapplock`; no hay "check-then-insert" en PHP |
| 5 | Reprogramación | 1 | Detalle de la cita → Reprogramar (mismo asistente) → nueva cita; la original queda REPROGRAMADA y enlazada | Una sola transacción: crea la nueva, libera el slot original, historial y bitácora |
| 6 | Recepción | 1 | `recepcion` → `/recepcion`: agenda del día, buscar paciente, crear paciente, reservar para él, cancelar con motivo | Motivos 4–5 solo para personal; "Otro" exige observación |
| 7 | Médico | 1 | `medico` → `/medico/hoy`: citas del día; marcar ATENDIDA / NO ASISTIÓ (no antes de la hora de inicio) | Transición de estados validada en SQL (`CITA_NO_INICIADA`) |
| 8 | Admin agenda | 1.5 | `administrador` → `/admin/agendas/nueva` (generar rango), `/admin/bloqueos` (intentar bloquear un rango con reservas → rechazo `RESERVAS_EN_INTERVALO`), `/admin/agendas/dia` | Agendas sin solapes por médico ni consultorio |
| 9 | RBAC | 1 | Con `paciente`, abrir `/admin/usuarios` → 403. Tabla de `docs/ROLES_PERMISOS.md` | Doble control: middleware `permission:` y `seg.usp_ExigirPermiso` dentro de cada SP |
| 10 | Auditoría | 1 | `auditor` → `/auditoria`: filtrar por correlation id de una acción anterior; `/reportes` | Se registran éxitos y rechazos, sin datos sensibles |
| 11 | SQL / SP | 1.5 | `docs/CATALOGO_STORED_PROCEDURES.md`; en `sqlcmd`: `EXEC api.usp_ReservaCrear` con el id del auditor → `SIN_PERMISO`; en `db-validate.ps1`, las comprobaciones `nexa_app_role: EXECUTE en api` y `DENY SELECT en clin/seg/audit` | El rol de la app solo puede `EXECUTE`/`SELECT` sobre `api` |
| 12 | Tests | 1 | `.\scripts\test.ps1` → 56 pruebas; `.\scripts\db-validate.ps1` → 187/187 | Unit/Feature sin BD; Integration solo contra `_Test` |

## Preguntas probables

**¿Por qué no Eloquent?** Porque el requisito es que SQL Server sea la autoridad. Un modelo Eloquent permitiría `->save()`
directo a tablas. Los modelos de `app/Models` documentan el dominio (estados, motivos, tabla) y los DTO transportan datos.
Además, el rol de BD de la aplicación tiene `DENY` sobre `clin`/`seg`/`audit`.

**¿Qué pasa si dos personas reservan el mismo horario a la vez?** Ambas entran al SP; `sp_getapplock` serializa por
horario y paciente; la segunda ve el slot ocupado (`SLOT_OCUPADO`). Aunque se saltara el lock, el índice único filtrado
`UX_Reservas_HorarioOcupado` impide la segunda fila. Probado con 6 procesos reales.

**¿Y si el usuario hace doble clic?** El formulario lleva una `IdempotencyKey` (UUID). El reintento devuelve la misma
reserva (`RESERVA_EXISTENTE`); la misma clave con otros datos se rechaza (`IDEMPOTENCIA_CONFLICTO`).

**¿Cómo evitan pisar cambios de otro usuario?** `ROWVERSION` (`VersionFila`) viaja en el formulario; si cambió, el SP
responde `CONFLICTO_EDICION` y la UI pide recargar.

**¿Dónde están las reglas de horario (anticipación, plazo de cancelación, límite de reservas)?** En
`seg.ConfiguracionSistema`, editable por el superadministrador; los SP las leen con `seg.fn_ConfigEntero`.

**¿Cómo migran los datos del sistema VB.NET?** Con `ReservasMedicasWeb_MIGRATE_FROM_V1.sql`, opcional, de solo lectura
sobre `ReservasMedicasDB`. Las contraseñas PBKDF2 se aceptan y se re-hashean a bcrypt en el primer login.

**¿Qué pasa si SQL Server cae?** El executor traduce errores de conexión a `DatabaseUnavailableException` → página 503
amigable, con correlation id para soporte.

**¿Zona horaria?** Las citas se guardan en hora local de la clínica (America/Lima) y las marcas técnicas en UTC;
`clin.fn_AhoraLocal` es la única fuente de "ahora".

**¿Qué falta para producción?** Crear un login SQL dedicado mapeado solo a `nexa_app_role` (en desarrollo se usa
autenticación de Windows), HTTPS, `SESSION_SECURE_COOKIE=true`, `APP_DEBUG=false` y QA visual en móviles.
