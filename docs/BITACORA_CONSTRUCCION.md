# Bitácora de construcción

Construcción realizada el 2026-09-18 con Claude Code siguiendo `PROMPT_CLAUDE_LARAVEL_MEDICAL_APPOINTMENTS.md`.
Esta bitácora no contiene contraseñas, tokens ni cadenas de conexión con secretos.

## 1. Auditoría inicial
- Repositorio `D:\Git-Hub\medical-appointments-laravel`: git inicializado, sin commits.
- Entorno comprobado **antes** de instalar nada: PHP 8.3 (Laragon) con `sqlsrv`/`pdo_sqlsrv`, Composer, Node 20 / npm 10,
  SQL Server 2022 local con autenticación de Windows (`sqlcmd -S localhost -E`). No hizo falta instalar nada global ni usar Docker.
- La ruta legacy indicada en el prompt (`D:\Devs\…`) no existía; el proyecto VB.NET se encontró en
  `D:\Git-Hub\medical-appointment-system-vbnet`.

## 2. Legacy (solo lectura)
- WinForms .NET 4.8 con 8 formularios, repositorios `Data/*Repository.vb` que llaman a `dbo.usp_*`, e `InicializadorBD` con
  SQL directo.
- BD `ReservasMedicasDB`: esquema `dbo`, 10 tablas, 23 SP, 2 roles, contraseñas PBKDF2-SHA256.
- No se modificó ni el proyecto ni la BD legacy. Mapeo completo en `MIGRACION_DESDE_VBNET.md`.

## 3. SQL V2
- `ReservasMedicasWeb_MASTER.sql` idempotente y no destructivo (`CREATE OR ALTER`, `IF NOT EXISTS`, sin `DROP DATABASE`
  ni `MERGE`): 24 tablas en `seg`/`clin`/`audit`, 16 vistas y 60 SP en `api`, 10 funciones, 13 SP internos, rol `nexa_app_role`.
- `VALIDATE.sql` (187 comprobaciones), `RESET_DEV.sql` (solo BD `ReservasMedicasWeb`/`_Test` y con `-v CONFIRMAR_RESET=SI`),
  `MIGRATE_FROM_V1.sql` (lee `ReservasMedicasDB` sin modificarla).

## 4. Laravel
- Laravel 12 + Blade + Vite + Bootstrap 5.3. Sin Eloquent para escribir: los modelos de `app/Models` son documentales.

## 5. Drivers
- `sqlsrv` 5.12 ya presente en Laragon; conexión por autenticación de Windows en desarrollo (`DB_USERNAME` vacío).
  Para producción se documenta un login dedicado con solo `nexa_app_role`.

## 6. Repositories
- 10 repositorios `SqlServer*Repository` detrás de interfaces; nombres de SP como constantes de clase.
- `StoredProcedureExecutor`: lista blanca `api.usp_*`/`api.vw_*`, parámetros con nombre, `ORDER BY`/filtros validados,
  mapeo de errores (50403 → permiso, 50409/1205/1222 → concurrencia, conexión → 503).

## 7. Services
- 11 servicios (reservas, agenda, pacientes, médicos, catálogo, auditoría, reportes, dashboard, auth, admin, seed demo).
- `DemoSeedService` / `php artisan clinic:seed-demo`: datos ficticios **solo vía SP**, idempotente.

## 8. Auth
- Guard propio `nexa-session` sobre `api.usp_AuthObtenerUsuario` y `api.usp_AuthObtenerPermisosUsuario`; bcrypt 12; PBKDF2 legacy aceptado y re-hasheado;
  hash ficticio para igualar tiempos; bloqueo por intentos en SQL; throttle por login+IP e IP.

## 9. RBAC
- 6 roles, 25 permisos. Middleware `permission:` (OR) + verificación dentro de cada SP (`seg.usp_ExigirPermiso` /
  `seg.fn_TienePermiso`). Permisos refrescados cada 5 minutos en sesión.

## 10. UI
- 63 vistas Blade, 16 componentes, layout con menú lateral por permisos y `offcanvas` en móvil, enlace "saltar al contenido",
  estados de carga/errores amigables, páginas 403/404/419/429/500/503 con código de seguimiento.

## 11. Reservas
- Asistente de 5 pasos reutilizado para reprogramar; `IdempotencyKey` por formulario; `VersionFila` en cancelar/reprogramar/cerrar.

## 12. Admin
- Usuarios, médicos (especialidades/sedes), agendas (individual y por rango), bloqueos, catálogos, configuración, reservas.

## 13. Testing
- Unit (24) y Feature (17) sin BD; Integration (15) contra `ReservasMedicasWeb_Test`, que se omite si la BD no termina en `_Test`.

## 14. Problemas encontrados
| # | Problema | Causa |
|---|---|---|
| 1 | `Msg 1934` al crear índices filtrados | `sqlcmd` ejecuta con `QUOTED_IDENTIFIER OFF` por defecto |
| 2 | `Msg 425` en `usp_AuthRegistrarFallo` | Variable `INT` asignada desde la columna `TINYINT` `IntentosFallidos` dentro de un `UPDATE` |
| 3 | `Msg 102` en `usp_ReservaReprogramar` | Error de sintaxis en una llamada con parámetro de salida |
| 4 | `Msg 468` en VALIDATE | Conflicto de intercalación entre `Modern_Spanish_CI_AS` (BD) y `Latin1_General_CI_AS_KS_WS` (catálogo del sistema) |
| 5 | `Msg 207`/`208` en consultas de verificación | Nombres de columnas/tablas equivocados en consultas ad hoc (p. ej. `AgendaId`, `audit.BitacoraSistema`) |
| 6 | Smoke HTTP: `parse_ini_file` falla con `.env` | `.env` no es INI válido |
| 7 | Navegador automatizado sin acceso a `127.0.0.1:8123` | El navegador controlado no alcanza el servidor local (`ERR_CONNECTION_REFUSED`) |
| 8 | Fin de línea CRLF en un test generado | Archivo escrito desde Python en Windows |
| 9 | Documentación con detalles inexactos (orden de reprogramación, códigos de bloqueo, pasos del asistente) | Redactada antes de releer el SQL |
| 10 | Limpieza incompleta en `CriticalValidationsTest` | `listAgendas` pagina a máximo 100 filas y la agenda no estaba en la primera página |
| 11 | Paciente de prueba rechazado con `FECHA_NACIMIENTO_INVALIDA` | La fecha de nacimiento es obligatoria en V2 |

## 15. Soluciones
1. `SET QUOTED_IDENTIFIER ON`, `ANSI_NULLS ON`, `ANSI_PADDING ON` y `ANSI_WARNINGS ON` al inicio del MASTER.
2. `@Intentos` declarado `TINYINT`.
3. Sintaxis corregida; el MASTER se re-ejecutó sin errores (es idempotente).
4. `COLLATE DATABASE_DEFAULT` en las comparaciones con `sys.*`.
5. Consultas corregidas tras revisar el esquema.
6. Lectura con `Dotenv::parse` sin imprimir valores.
7. QA responsive solo estático; **pendiente** verificación visual manual.
8. `pint` normalizó el archivo; `.gitattributes` fija `eol=lf`.
9. Cada documento se contrastó con el SQL y el código y se corrigió.
10. Búsqueda de la agenda filtrando por médico y fecha; se verificó que dos corridas seguidas no dejan agendas ni reservas activas.
11. Se agregó la fecha de nacimiento al paciente de prueba.

## 16. QA
- `php artisan test`: **56 passed (249 assertions)**. `pint --test`: OK. `npm run build`: OK. VALIDATE: **187/187**.
- Smoke HTTP con `php artisan serve`: login por rol, reserva, doble envío idempotente, cancelación, versión vieja,
  CSRF 419, reprogramación por UI (`NX-00000001` → `NX-00000046`) con bitácora `RESERVA_REPROGRAMADA`.
- Verificación de código (§113): en `app/`, `routes/` y `database/` no hay `DB::table(`, `::create(`, `->save(`,
  `->delete(`, `Model::query(` ni `DB::insert/update/delete/statement/unprepared`. Las 5 coincidencias de `->update(` son
  métodos de servicios/repositorios (`DoctorService::update` → `api.usp_AdminMedicoActualizar`, etc.), no Eloquent.

## 17. Estado final
- Funcionalidad implementada según el alcance del prompt; commit local sin push. Estado por criterio en `evidencias/CHECKLIST.md`.
- Pendientes manuales: QA visual responsive en navegador/móvil, cierre de cita del médico por la UI, login SQL dedicado para producción, completar las
  contraseñas demo en `.env` antes de `clinic:seed-demo`.
