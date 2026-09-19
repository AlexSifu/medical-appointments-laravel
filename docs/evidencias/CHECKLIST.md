# Checklist de terminación (§124)

Fecha de verificación: 2026-09-18. ✅ verificado · ⚠️ verificado en parte (ver nota) · ❌ no verificado.

| Criterio | Estado | Evidencia |
|---|---|---|
| Laravel inicia | ✅ | `php artisan serve` en `127.0.0.1:8123`; `/login` responde 200 |
| SQL Server conecta | ✅ | `check-environment.ps1`: SQL Server 16.0 Developer, BD `ReservasMedicasWeb` y `_Test` presentes |
| Blade renderiza | ✅ | Smoke HTTP de todas las pantallas por rol; tests Feature que renderizan vistas |
| Login funciona | ✅ | `LoginTest` (6) + smoke HTTP de los 6 roles (login → `/dashboard`) |
| Roles funcionan | ✅ | `AccessControlTest` (403 del paciente en admin), `ReservationRulesTest` (`SIN_PERMISO` desde SQL), VALIDATE RBAC 9/9 |
| Paciente reserva | ✅ | Smoke HTTP por el asistente; `BookingFlowTest` |
| Doble reserva imposible | ✅ | `ReservationConcurrencyTest`: 6 procesos → 1 éxito; índice `UX_Reservas_HorarioOcupado` (VALIDATE) |
| Cancelación funciona | ✅ | Smoke HTTP; `ReservationRulesTest` (versión vieja → `CONFLICTO_EDICION`); `CriticalValidationsTest` (`FUERA_DE_PLAZO` para paciente, personal sí puede) |
| Reprogramación funciona | ✅ | Smoke HTTP `NX-00000001` → `NX-00000046` sin duplicado al reintentar; `ReservationRulesTest` (slot original liberado); `CriticalValidationsTest` (slot ocupado) |
| Recepción funciona | ✅ | Reserva para un paciente elegido (`BookingFlowTest`, integración); pantallas `/recepcion` y pacientes en el smoke |
| Médico funciona | ⚠️ | Login y agenda del día renderizan en el smoke. Cerrar una cita (ATENDIDA/NO ASISTIÓ) está validado en SQL pero **no se ejercitó por HTTP** (requiere una cita ya iniciada) |
| Admin funciona | ✅ | Pantallas de admin en el smoke; SP de admin ejercitados en `CriticalValidationsTest` (crear/desactivar agenda, bloquear, activar/desactivar médico, configuración) |
| Auditoría funciona | ✅ | `/auditoria` y `/reportes` renderizan para el auditor; registro `RESERVA_REPROGRAMADA` verificado en `audit.BitacoraSistema` |
| SP son autoridad | ✅ | Sin CRUD directo en `app/` (grep §113); executor con lista blanca `api.` (`StoredProcedureExecutorTest`); `nexa_app_role` con `DENY` en `clin`/`seg`/`audit` (VALIDATE) |
| Tests pasan | ✅ | `php artisan test`: 56 passed (249 assertions); `pint --test` OK |
| Build pasa | ✅ | `npm run build` OK (Vite) |
| Responsive pasa | ❌ | Solo revisión estática (viewport, grilla Bootstrap, `offcanvas`, `table-responsive`). El navegador automatizado no pudo conectarse al servidor local. **Pendiente de revisión manual** a 375–414 px |
| Docs completas | ✅ | README + 11 documentos en `docs/` + este checklist |

## Resultados de comandos

```
scripts/check-environment.ps1  → Entorno listo.
scripts/db-validate.ps1        → Comprobaciones: 187  Correctas: 187  Fallidas: 0  VALIDACION: OK
php artisan test               → Tests: 56 passed (249 assertions)
  Unit 24 (43) · Feature 17 (70) · Integration 15 (136)
vendor/bin/pint --test         → passed
npm run build                  → built
```

## Pendientes manuales
1. QA visual responsive (login, asistente de reserva, mis citas, agenda del día) en un navegador real y en móvil.
2. Cerrar una cita como médico por la UI (necesita una cita cuya hora ya haya empezado).
3. Producción: login SQL dedicado miembro solo de `nexa_app_role`, HTTPS, `SESSION_SECURE_COOKIE=true`, `APP_DEBUG=false`.
4. Completar `DEMO_*_PASSWORD` en `.env` en cada instalación nueva antes de `php artisan clinic:seed-demo`.
