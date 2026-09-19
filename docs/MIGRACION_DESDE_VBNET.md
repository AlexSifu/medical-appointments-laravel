# Migración desde VB.NET (WinForms) a Laravel

| | Legacy (V1) | Web (V2) |
|---|---|---|
| Proyecto | `D:\Git-Hub\medical-appointment-system-vbnet` (sin modificar) | `D:\Git-Hub\medical-appointments-laravel` |
| UI | WinForms, 8 formularios | Blade + Bootstrap 5.3, responsive |
| BD | `ReservasMedicasDB` (esquema `dbo`, 10 tablas, 23 SP) — **intacta** | `ReservasMedicasWeb` (`api`/`seg`/`clin`/`audit`: 24 tablas, 16 vistas, 60 SP públicos) |
| Roles | 2 (ADMINISTRADOR, USUARIO) | 6 roles + 25 permisos |
| Acceso a datos | Repositorios VB → `dbo.usp_*` (+ SQL directo en `InicializadorBD`) | Repositorios PHP → solo `api.usp_*` / `api.vw_*` |
| Contraseñas | PBKDF2-SHA256 | bcrypt (acepta PBKDF2 legacy y lo re-hashea en el primer login) |

Se conservó la idea central del legacy —la lógica en procedimientos almacenados y una capa de repositorios delgada— y se
reforzó: permisos por SP, concurrencia con locks y `SERIALIZABLE`, idempotencia, control optimista y bitácora completa.

## Mapa de formularios → módulos web

| Formulario VB.NET | Módulo Laravel | Rutas | Controlador / vistas | SP principales |
|---|---|---|---|---|
| `FrmLogin` | Autenticación / Blade login | `GET/POST /login`, `POST /logout` | `Auth\LoginController`, `auth/login.blade.php` | `AuthObtenerUsuario`, `AuthRegistrarFallo/Exito/Logout`, `AuthObtenerPermisosUsuario` |
| `FrmPrincipal` | Dashboard por rol + menú lateral según permisos | `GET /dashboard` | `DashboardController`, `dashboard/{patient,reception,doctor,admin,auditor}.blade.php` | `DashboardResumen`, `DashboardSerieDiaria` |
| `FrmMedicos` | Admin / Doctor module | `/admin/medicos…`, `/medicos` (directorio) | `Admin\DoctorController`, `DoctorDirectoryController` | `AdminMedicoCrear/Actualizar/AsignarEspecialidad/AsignarSede/CambiarEstado`, `MedicosBuscar` |
| `FrmAgenda` | Schedule module (agendas, rango, bloqueos, vista del día) | `/admin/agendas…`, `/admin/bloqueos…` | `Admin\ScheduleController`, `admin/schedules/*` | `AdminAgendaCrear/GenerarRango/Listar/Desactivar`, `AdminHorarioBloquear/Desbloquear`, `AgendaObtenerDia` |
| `FrmReservar` | Appointment wizard (5 pasos: especialidad → médico/sede → fecha → horario → confirmar) | `/reservar…`, `POST /reservas` | `BookingController`, `booking/create.blade.php` + `booking.js` | `AgendaFechasDisponibles`, `AgendaHorariosEstado`, `ReservaCrear`, `RecepcionReservaCrear` |
| `FrmMisReservas` | Patient appointments (próximas/pasadas, detalle, cancelar, reprogramar) | `/mis-citas`, `/reservas/{id}…` | `ReservationController`, `patient/appointments.blade.php`, `reservations/*` | `ReservaMisReservas`, `ReservaObtenerDetalle`, `ReservaCancelar`, `ReservaReprogramar` |
| `FrmReservasAdmin` | Admin reservations (búsqueda con filtros, acciones) | `/admin/reservas`, `/recepcion` | `Admin\ReservationAdminController`, `Reception\ReceptionController` | `AdminReservasBuscar`, `AgendaObtenerDia` |

Módulos nuevos sin formulario equivalente en V1: recepción de pacientes (`/recepcion/pacientes`), agenda del médico
(`/medico/hoy`, `/medico/agenda`), usuarios y roles (`/admin/usuarios`), especialidades y sedes/consultorios, bitácora
(`/auditoria`) y reportes con exportación CSV (`/reportes`).

## Mapa de clases

| VB.NET | Laravel |
|---|---|
| `Data/ConexionBD.vb` | `config/database.php` (sqlsrv) + `Support/Database/StoredProcedureExecutor` |
| `Data/AutenticacionRepository.vb` | `Repositories/SqlServer/SqlServerAuthRepository` |
| `Data/ReservaRepository.vb` | `SqlServerReservationRepository`, `SqlServerScheduleRepository`, `SqlServerCatalogRepository` |
| `Data/AdministracionRepository.vb` | `SqlServerDoctorRepository`, `SqlServerScheduleRepository`, `SqlServerCatalogRepository`, `SqlServerUserRepository` |
| `Data/InicializadorBD.vb` (SQL directo) | `php artisan clinic:seed-demo` → `DemoSeedService` (solo vía SP) + `api.usp_SistemaInicializarSuperadmin` |
| `Services/*.vb` | `app/Services/*` |
| `Helpers/PasswordHelper.vb` | `Support/Security/LegacyPbkdf2Hasher` (compatibilidad) + bcrypt de Laravel |
| `Models/UsuarioSesion.vb` | `DTO/AuthenticatedUserData` + `Models/User` (guard de sesión) |

## Base de datos: V1 → V2

`database/sql/ReservasMedicasWeb_MIGRATE_FROM_V1.sql` es **de solo lectura**: detecta ambas BD, compara conteos y lista lo que
falta. No hay migración automática de datos porque V1 no tiene información que V2 exige:

| Dato V2 obligatorio | En V1 |
|---|---|
| Ficha de paciente con documento único y fecha de nacimiento | No existe (usuario = paciente) |
| Sede y consultorio de cada agenda | No existen |
| Especialidad de la agenda | No se registra |
| Roles granulares | Solo ADMINISTRADOR / USUARIO |
| Auditoría en UTC | Fechas locales sin zona |

Migrarlo obligaría a inventar datos. Procedimiento asistido recomendado si se decide migrar:

1. `BACKUP DATABASE` de ambas BD con `COPY_ONLY, CHECKSUM` (el script imprime los comandos).
2. Ejecutar `MIGRATE_FROM_V1.sql` y revisar el diagnóstico.
3. Crear en V2 (UI de administración) sedes, consultorios y especialidades reales.
4. Médicos: crear con `api.usp_AdminMedicoCrear` usando los datos de `dbo.Medicos` + especialidad asignada.
5. Usuarios: crear con `api.usp_AdminUsuarioCrear`; el hash legacy se guarda con algoritmo `PBKDF2_SHA256` en el formato
   `pbkdf2_sha256$<iteraciones>$<salt b64>$<hash b64>` (sección 5 del script) y Laravel lo convierte a bcrypt en el primer login.
6. Pacientes: completar documento/fecha de nacimiento con el paciente (no se inventan).
7. Reservas futuras: recrearlas con `api.usp_RecepcionReservaCrear` para que pasen todas las validaciones; las históricas
   pueden quedar consultables en V1.
8. `ReservasMedicasWeb_VALIDATE.sql` y pruebas de integración.

## Qué se descartó del legacy y por qué

- SQL directo desde la aplicación (`InicializadorBD`) → todo pasa por SP.
- Roles con comparaciones de texto en los formularios → permisos verificados en ruta **y** en SP.
- Sin control de concurrencia en reservas → índice único filtrado + locks + idempotencia.
