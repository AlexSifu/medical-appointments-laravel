# PROMPT MAESTRO — CLAUDE CODE
## Prueba Técnica E2 — PHP Laravel
### Migración integral VB.NET → Laravel + Blade + SQL Server
### Repositorio: `D:\Git-Hub\medical-appointments-laravel`

---

# 1. MISIÓN

Construye la tercera parte obligatoria de la prueba técnica: migrar integralmente a Laravel el sistema de reservas médicas desarrollado previamente en VB.NET / .NET Framework 4.8.

La solución debe:

- conservar las capacidades del sistema anterior;
- ampliar los casos de negocio que quedaron fuera;
- usar Blade obligatoriamente;
- organizar correctamente rutas, controladores, modelos y vistas;
- mantener SQL Server;
- llevar la lógica crítica de negocio a Stored Procedures, Views, Functions, constraints e índices;
- usar Laravel principalmente como capa web, seguridad HTTP, orquestación y presentación;
- tener diseño profesional, responsive y accesible;
- quedar probado, documentado y listo para demostración.

No construyas una SPA. No uses React/Vue como frontend principal.

---

# 2. RUTA DE TRABAJO

Trabaja en:

```text
D:\Git-Hub\medical-appointments-laravel
```

Antes de modificar:

```powershell
cd D:\Git-Hub\medical-appointments-laravel
Get-Location
git status
```

No borres `.git`.
No hagas `git push`.
No sobrescribas otros proyectos.

---

# 3. PROYECTO LEGACY — SOLO LECTURA

Debes estudiar el proyecto VB.NET antes de diseñar Laravel.

Buscar primero:

```text
D:\Devs\medical-appointment-system-vbnet
D:\Devs\medical-appointment-system-vbnet\db\ReservasMedicas.sql
```

Si no está allí, buscar:

```text
D:\Git-Hub\medical-appointment-system-vbnet
D:\Git-Hub\medical-appointment-system-vbnet\db\ReservasMedicas.sql
```

Revisar, cuando existan:

```text
Data/
Forms/
Helpers/
Models/
Services/
App.config
db/ReservasMedicas.sql
README.md
```

NO modificar el proyecto legacy.

---

# 4. CONTEXTO LEGACY

La aplicación anterior:

```text
Forms
→ Services
→ Repositories
→ Stored Procedures
→ SQL Server
```

Base:

```text
ReservasMedicasDB
```

Tablas originales:

```text
Roles
Usuarios
Especialidades
Medicos
MedicoEspecialidad
AgendasMedicas
HorariosMedicos
EstadosReserva
Reservas
BitacoraSistema
```

Views originales:

```text
vw_MedicosDisponibles
vw_AgendaMedicaDetalle
vw_HorariosDisponibles
vw_ReservasDetalle
vw_BitacoraDetalle
```

Stored Procedures originales:

```text
usp_RegistrarBitacora
usp_ObtenerUsuarioAutenticacion
usp_RegistrarLoginExitoso
usp_RegistrarLoginFallido
usp_ObtenerMedicosDisponibles
usp_ObtenerEspecialidades
usp_ObtenerEstadosReserva
usp_ObtenerFechasDisponiblesMedico
usp_ObtenerAgendaMedica
usp_ObtenerHorariosDisponibles
usp_CrearReserva
usp_ObtenerMisReservas
usp_CancelarReserva
usp_ObtenerReservasAdmin
usp_ObtenerBitacoraAdmin
usp_AdminCrearMedico
usp_AdminActualizarMedico
usp_AdminAsignarEspecialidadMedico
usp_AdminCrearAgendaMedica
usp_AdminObtenerAgendasMedico
usp_AdminBloquearHorarioMedico
usp_AdminDesbloquearHorarioMedico
usp_AdminDesactivarAgendaMedica
```

Fortalezas ya implementadas:

```text
PBKDF2-SHA256
salt
100000 iteraciones
5 intentos fallidos
bloqueo temporal
roles
bitácora
transacciones
SERIALIZABLE
sp_getapplock
índices únicos filtrados
prevención de doble reserva
prevención de solapamiento
cancelación histórica
```

Conserva esas fortalezas y evoluciona la solución.

---

# 5. REGLA ARQUITECTÓNICA DE DATOS — OBLIGATORIA

Las reglas críticas de negocio y toda mutación del dominio deben estar en SQL Server.

Laravel NO debe hacer CRUD directo de negocio sobre tablas.

Prohibido para operaciones del dominio:

```php
Reserva::create(...)
Medico::where(...)->update(...)
DB::table('Reservas')->insert(...)
```

Flujo obligatorio:

```text
Blade
→ Controller
→ FormRequest
→ Service
→ Repository
→ Stored Procedure / View / Function
→ SQL Server
```

Los Controllers no conocen SQL.

Los Services no construyen SQL.

Solo los Repositories conocen `DB::select`, `EXEC`, Views y Functions.

---

# 6. EXCEPCIONES CORRECTAS PARA LARAVEL

Laravel sí debe encargarse de:

```text
CSRF
sesiones
cookies
rate limiting HTTP
validación de formato
password hashing
password verification
middleware
render Blade
mensajes flash
logging técnico
```

SQL Server sigue siendo autoridad final para:

```text
autorización crítica
reglas de reserva
agenda
concurrencia
transacciones
integridad
auditoría de negocio
```

---

# 7. MOTOR DE BASE DE DATOS

Mantener:

```text
Microsoft SQL Server
```

No migrar a MySQL/MariaDB.

Preferir:

```text
Laragon/PHP local
+
SQL Server existente
```

Docker solo si existe una necesidad técnica real.

---

# 8. AUDITORÍA INICIAL OBLIGATORIA

Antes de escribir código, reporta:

```text
AUDITORÍA INICIAL LARAVEL

Repositorio:
Proyecto legacy:
Script SQL legacy:
PHP:
Composer:
Node:
npm:
SQL Server:
sqlcmd:
sqlsrv:
pdo_sqlsrv:
Git:
Puertos:
Riesgos:
Laravel propuesto:
BD nueva:
Arquitectura:
Plan por fases:
```

Ejecutar:

```powershell
php -v
composer --version
node --version
npm --version
php -m
php -m | findstr /I "sqlsrv pdo_sqlsrv"
git status
```

No instalar nada sin comprobar primero.

---

# 9. DRIVER SQL SERVER

Si existen:

```text
sqlsrv
pdo_sqlsrv
```

usar la instalación actual.

Si faltan:

1. comprobar versión exacta de PHP;
2. comprobar TS/NTS;
3. comprobar x64;
4. comprobar ODBC Driver;
5. instalar únicamente binaries compatibles;
6. no romper Laragon;
7. documentar lo realizado.

Docker es último recurso, no primera opción.

---

# 10. LARAVEL

Usar una versión estable compatible con PHP 8.3.30.

Documentar versiones reales:

```text
PHP
Laravel
Composer
Node
npm
SQL Server
ODBC
```

Si el repositorio ya contiene `.git`, no lo destruyas al ejecutar `composer create-project`.

---

# 11. IDENTIDAD

Nombre:

```text
Nexa Salud
```

Descriptor:

```text
Gestión de Citas Médicas
```

Sistema demostrativo, sin identidad de clínica real.

---

# 12. FRONTEND

Usar:

```text
Blade
Vite
Bootstrap 5.3
Bootstrap Icons
JavaScript vanilla
```

Chart.js solo si aporta al dashboard.

No usar SPA.

Diseño:

- profesional;
- sanitario;
- moderno;
- sobrio;
- accesible;
- responsive.

Paleta sugerida:

```text
#0F2742 navy
#2563EB blue
#0F766E teal
#0891B2 cyan
#15803D success
#B45309 warning
#B91C1C danger
#0F172A text
#475569 secondary
#E2E8F0 border
#F8FAFC background
#FFFFFF
```

---

# 13. ESTRUCTURA LARAVEL

Crear una estructura clara:

```text
app/
├── DTO/
├── Exceptions/
├── Http/
│   ├── Controllers/
│   │   ├── Auth/
│   │   ├── Admin/
│   │   ├── Doctor/
│   │   ├── Reception/
│   │   └── Patient/
│   ├── Middleware/
│   └── Requests/
├── Models/
├── Policies/
├── Repositories/
│   ├── Contracts/
│   └── SqlServer/
├── Services/
└── Support/

database/
├── seeders/
└── sql/

resources/
├── css/
├── js/
└── views/

routes/
├── web.php
└── auth.php

tests/
├── Feature/
├── Unit/
└── Integration/

docs/
scripts/
```

---

# 14. MODELOS

La prueba exige organización de modelos.

Crear representaciones de:

```text
User
Role
Permission
Patient
Doctor
Specialty
Branch
ConsultingRoom
Schedule
TimeSlot
Reservation
ReservationStatus
AuditLog
```

No usar Models para saltarse Stored Procedures.

Pueden servir para:

```text
casts
constantes
representación del dominio
documentación de relaciones
```

La persistencia real sigue en Repository + SP.

---

# 15. DISEÑO ROBUSTO DE BD

Leer primero la V1.

Luego evolucionar el modelo.

Evaluar como mínimo:

## Seguridad

```text
Roles
Permisos
RolPermiso
Usuarios
UsuarioRol
```

## Clínica

```text
Pacientes
Medicos
Especialidades
MedicoEspecialidad
Sedes
Consultorios
MedicoSede
TiposAtencion
```

## Agenda

```text
PlantillasAgenda
AgendasMedicas
HorariosMedicos
BloqueosMedico
```

## Reserva

```text
EstadosReserva
MotivosCancelacion
Reservas
ReservaHistorial
```

## Plataforma

```text
ConfiguracionSistema
BitacoraSistema
```

No agregar tablas sin propósito.

---

# 16. SCHEMAS SQL

Si no complica innecesariamente:

```text
seg
clin
audit
api
```

Ejemplo:

```text
seg.Usuarios
seg.Roles
seg.Permisos

clin.Pacientes
clin.Medicos
clin.Reservas

audit.BitacoraSistema

api.usp_ReservaCrear
api.vw_ReservasDetalle
```

---

# 17. RBAC

Roles:

```text
SUPERADMIN
ADMINISTRADOR
RECEPCIONISTA
MEDICO
PACIENTE
AUDITOR
```

No usar únicamente `RolId = 1`.

Permisos sugeridos:

```text
usuarios.ver
usuarios.crear
usuarios.editar
usuarios.roles

pacientes.ver
pacientes.crear
pacientes.editar

medicos.ver
medicos.crear
medicos.editar

especialidades.gestionar
sedes.gestionar
consultorios.gestionar

agenda.ver
agenda.gestionar
agenda.bloquear

reservas.propias
reservas.crear
reservas.reprogramar
reservas.cancelar
reservas.gestionar
reservas.estado

auditoria.ver
reportes.ver
configuracion.gestionar
```

---

# 18. AUTORIZACIÓN DOBLE

Laravel:

```text
Middleware / Policy
```

SQL:

```text
SP + permisos efectivos
```

Aunque una ruta se manipule, el SP debe rechazar actores sin permiso.

---

# 19. PACIENTE ≠ USUARIO

Separar `Usuarios` y `Pacientes`.

Un paciente puede existir sin cuenta web porque recepción puede registrarlo.

Paciente sugerido:

```text
PacienteId
UsuarioId nullable
TipoDocumento
NumeroDocumento
Nombres
Apellidos
FechaNacimiento
Sexo opcional
Telefono
Email
Direccion opcional
ContactoEmergencia opcional
Activo
FechaCreacionUtc
FechaModificacionUtc
VersionFila
```

No implementar historia clínica.

---

# 20. MEDICOS

Campos razonables:

```text
MedicoId
UsuarioId nullable
CMP
Nombres
Apellidos
Telefono
Email
Activo
FechaCreacionUtc
FechaModificacionUtc
VersionFila
```

Mantener N:N con Especialidades.

Una puede ser principal.

---

# 21. SEDES Y CONSULTORIOS

Agregar:

```text
Sedes
Consultorios
MedicoSede
```

Evitar solapamiento de consultorio.

Ejemplo demo:

```text
Sede Central
Sede Norte
```

---

# 22. TIPOS DE ATENCION

Catálogo:

```text
PRESENCIAL
VIRTUAL
```

No implementar videollamada real.

---

# 23. AGENDA ROBUSTA

Mantener:

```text
AgendasMedicas
HorariosMedicos
```

Evaluar `PlantillasAgenda` para recurrencia.

Reglas:

- médico no se superpone;
- consultorio no se superpone;
- duración válida;
- fecha válida;
- slots no duplicados;
- agenda activa;
- generación idempotente.

---

# 24. BLOQUEOS

Permitir:

```text
bloqueo de slot
ausencia
reunión
capacitación
licencia
mantenimiento
```

No bloquear un intervalo con reservas confirmadas sin flujo explícito.

---

# 25. ESTADOS RESERVA

Como mínimo:

```text
CONFIRMADA
CANCELADA
REPROGRAMADA
ATENDIDA
NO_ASISTIO
```

Definir transiciones válidas.

No permitir transiciones arbitrarias.

---

# 26. HISTORIAL DE RESERVA

Crear `ReservaHistorial`.

Registrar:

```text
EstadoAnterior
EstadoNuevo
UsuarioActor
Motivo
FechaUtc
```

La reserva guarda estado actual.
El historial guarda trazabilidad.

---

# 27. REPROGRAMACIÓN ATÓMICA

`api.usp_ReservaReprogramar` debe:

1. validar actor;
2. validar reserva;
3. validar política;
4. validar nuevo slot;
5. obtener locks;
6. crear nueva reserva;
7. marcar anterior REPROGRAMADA;
8. vincular ambas;
9. crear historial;
10. auditar;
11. commit.

Ante cualquier error:

```text
ROLLBACK
```

---

# 28. CANCELACIÓN

Guardar:

```text
MotivoCancelacionId
ObservacionCancelacion
CanceladoPorUsuarioId
FechaCancelacionUtc
```

Paciente:

- solo reservas propias;
- política de anticipación.

Admin/Recepción:

- puede cancelar con permiso;
- debe indicar motivo.

---

# 29. CONFIGURACIÓN

Crear `ConfiguracionSistema`.

Ejemplo:

```text
HORAS_MINIMAS_CANCELACION
MAX_DIAS_RESERVA_FUTURA
```

No hardcodear reglas configurables en Blade.

---

# 30. CREAR RESERVA — VALIDACIONES

El SP debe validar:

- actor;
- permiso;
- paciente activo;
- médico activo;
- especialidad;
- sede;
- consultorio;
- agenda;
- slot;
- slot no bloqueado;
- fecha futura;
- slot libre;
- paciente sin cita solapada;
- médico sin cruce;
- consultorio sin cruce;
- política;
- idempotencia.

---

# 31. IDEMPOTENCIA

Crear:

```text
IdempotencyKey UNIQUEIDENTIFIER
```

Laravel genera UUID.

La misma clave dos veces:

```text
no crea duplicado
retorna la reserva existente
```

Crear índice único.

---

# 32. CONCURRENCIA

Preservar/mejorar:

```text
SET XACT_ABORT ON
TRY/CATCH
TRANSACTION
SERIALIZABLE donde aplique
sp_getapplock
unique indexes
```

Locks específicos:

```text
Reserva:Horario:{id}
Reserva:Paciente:{id}:{fecha}
Agenda:Medico:{id}:{fecha}
Agenda:Consultorio:{id}:{fecha}
```

No lock global.

---

# 33. DOBLE RESERVA

Debe ser imposible incluso con requests simultáneas.

No confiar solo en:

```text
IF NOT EXISTS
```

Debe existir protección transaccional + índice.

---

# 34. OCUPACIÓN DEL HORARIO

Diseñar qué estados ocupan slot.

Una opción robusta:

```text
Reservas.OcupaHorario BIT
```

controlado únicamente por SP.

Unique filtered index:

```text
HorarioMedicoId
WHERE OcupaHorario = 1
```

Si eliges otra solución, debe garantizar lo mismo.

---

# 35. ROWVERSION

Usar en entidades editables:

```text
Usuarios
Pacientes
Medicos
AgendasMedicas
HorariosMedicos
Reservas
```

Detectar conflictos de edición.

No sobrescribir silenciosamente.

---

# 36. FECHAS

Timestamps técnicos:

```text
SYSUTCDATETIME()
```

UI:

```text
America/Lima
```

Documentar conversión.

---

# 37. BITÁCORA

Mejorar:

```text
BitacoraId
UsuarioId
Accion
Entidad
EntidadId
Exitoso
Detalle
Ip
UserAgent
CorrelationId
FechaUtc
```

Nunca almacenar passwords/tokens.

---

# 38. EVENTOS AUDITADOS

Como mínimo:

```text
LOGIN_OK
LOGIN_FAIL
USUARIO_CREADO
ROL_ASIGNADO
PACIENTE_CREADO
MEDICO_CREADO
MEDICO_ACTUALIZADO
AGENDA_CREADA
HORARIO_BLOQUEADO
RESERVA_CREADA
RESERVA_CANCELADA
RESERVA_REPROGRAMADA
RESERVA_ATENDIDA
RESERVA_NO_ASISTIO
CONFIGURACION_ACTUALIZADA
```

---

# 39. PASSWORDS

Web nueva:

```text
Hash::make()
Hash::check()
```

No password en texto plano.

Si migras cuentas legacy PBKDF2:

1. detectar algoritmo;
2. verificar legacy en PHP;
3. login correcto;
4. generar hash Laravel;
5. llamar SP para actualizar;
6. registrar algoritmo nuevo.

No fingir compatibilidad si no la implementas.

---

# 40. LOGIN

Flujo:

```text
POST /login
→ LoginRequest
→ AuthService
→ AuthRepository
→ api.usp_AuthObtenerUsuario
→ verificar hash
→ api.usp_AuthRegistrarExito/Fallo
→ regenerar sesión
→ api.usp_AuthObtenerPermisosUsuario
```

Proteger con rate limit.

Mantener bloqueo de intentos.

---

# 41. RESULTADO ESTÁNDAR SP

Comandos deben devolver una fila:

```text
Exito
Codigo
Mensaje
EntidadId
```

Ejemplo:

```text
1 | RESERVA_CREADA | Reserva registrada | 1501
```

Negocio:

```text
0 | SLOT_OCUPADO | El horario ya no está disponible | NULL
```

---

# 42. PAGINACIÓN

SP de listado:

```text
Pagina
TamanoPagina
Filtros
Orden
```

Usar:

```text
OFFSET/FETCH
COUNT(*) OVER()
```

No paginar miles de filas en PHP.

---

# 43. VIEWS

Evaluar:

```text
api.vw_UsuariosRoles
api.vw_PacientesResumen
api.vw_MedicosCatalogo
api.vw_AgendasDetalle
api.vw_HorariosDisponibilidad
api.vw_ReservasDetalle
api.vw_AuditoriaDetalle
```

---

# 44. FUNCTIONS

Crear solo las necesarias.

Ejemplo:

```text
seg.fn_PermisosUsuario
clin.fn_DisponibilidadMedico
```

Preferir inline table-valued functions cuando sea apropiado.

---

# 45. SP — SEGURIDAD

Ejemplos:

```text
api.usp_AuthObtenerUsuario
api.usp_AuthRegistrarExito
api.usp_AuthRegistrarFallo
api.usp_AuthObtenerPermisosUsuario

api.usp_AdminUsuariosBuscar
api.usp_AdminUsuarioCrear
api.usp_AdminUsuarioActualizar
api.usp_AdminUsuarioAsignarRol
api.usp_UsuarioActualizarPasswordHash
```

---

# 46. SP — PACIENTES

```text
api.usp_PacienteObtener
api.usp_PacienteBuscar
api.usp_PacienteActualizarPerfil
api.usp_RecepcionPacienteCrear
api.usp_RecepcionPacienteActualizar
```

---

# 47. SP — MÉDICOS

```text
api.usp_MedicosBuscar
api.usp_MedicoObtener
api.usp_AdminMedicoCrear
api.usp_AdminMedicoActualizar
api.usp_AdminMedicoAsignarEspecialidad
api.usp_AdminMedicoAsignarSede
api.usp_AdminMedicoCambiarEstado
```

---

# 48. SP — AGENDA

```text
api.usp_AgendaFechasDisponibles
api.usp_AgendaHorariosDisponibles
api.usp_AgendaObtenerDia

api.usp_AdminAgendaCrear
api.usp_AdminAgendaGenerarRango
api.usp_AdminAgendaListar
api.usp_AdminHorarioBloquear
api.usp_AdminHorarioDesbloquear
api.usp_AdminAgendaDesactivar

api.usp_MedicoAgendaPropia
```

---

# 49. SP — RESERVAS

```text
api.usp_ReservaCrear
api.usp_ReservaObtenerDetalle
api.usp_ReservaMisReservas
api.usp_ReservaCancelar
api.usp_ReservaReprogramar

api.usp_RecepcionReservaCrear
api.usp_AdminReservasBuscar

api.usp_MedicoReservasDia
api.usp_MedicoMarcarAtendida
api.usp_MedicoMarcarNoAsistio
```

---

# 50. SP — REPORTES

```text
api.usp_AuditoriaBuscar
api.usp_DashboardResumen
api.usp_ReporteReservasPorEspecialidad
api.usp_ReporteOcupacionMedicos
api.usp_ReporteCancelaciones
api.usp_ReporteNoAsistencia
```

No crear SP solo por aumentar la cantidad.

---

# 51. SCRIPT SQL PRINCIPAL

Crear:

```text
database/sql/ReservasMedicasWeb_MASTER.sql
```

Debe crear:

```text
schemas
tables
constraints
indexes
functions
views
stored procedures
catálogos
roles
permissions
configuración base
validaciones
```

NO debe hacer `DROP DATABASE` por defecto.

---

# 52. RESET DEV

Crear separado:

```text
database/sql/ReservasMedicasWeb_RESET_DEV.sql
```

Con advertencia:

```text
DESTRUCTIVO
SOLO DESARROLLO
```

Nunca ejecutarlo automáticamente sobre una BD existente.

---

# 53. MIGRACIÓN V1 → V2

Si es viable:

```text
database/sql/ReservasMedicasWeb_MIGRATE_FROM_V1.sql
```

Debe preservar datos.

Antes:

- detectar V1;
- validar objetos;
- recomendar backup;
- comparar conteos.

Si no puede ser segura, documentar el plan en vez de inventar.

---

# 54. VALIDATE SQL

Crear:

```text
database/sql/ReservasMedicasWeb_VALIDATE.sql
```

Comprobar:

```text
schemas
tables
FK
checks
unique indexes
views
functions
SP
catálogos
roles
permissions
```

Terminar con resumen legible.

---

# 55. IDEMPOTENCIA DE SCRIPTS

Usar cuando aplique:

```text
IF NOT EXISTS
CREATE OR ALTER
```

Seeds no duplicables.

Evitar `MERGE` si una alternativa más simple es más segura.

---

# 56. ÍNDICES

Diseñar según consultas reales:

```text
username/email
documento paciente
CMP
medico/especialidad
medico/sede
agenda médico/fecha
agenda consultorio/fecha
slot agenda/hora
reserva paciente/estado
slot ocupado
fecha reserva
idempotency key
bitácora fecha/usuario
```

No duplicar índices equivalentes.

---

# 57. CONSTRAINTS

Usar:

```text
PK
FK
UNIQUE
CHECK
DEFAULT
```

Ejemplos:

```text
HoraFin > HoraInicio
DuracionMinutos válida
FechaNacimiento <= hoy
CMP no vacío
```

---

# 58. USUARIO SQL DE APLICACIÓN

Aplicar mínimo privilegio.

Laravel no debería usar `db_owner`.

Ideal:

```text
EXECUTE sobre api
SELECT sobre Views api
```

Sin DML directo a tablas del dominio.

Crear/documentar grants sin passwords reales.

---

# 59. DATOS DEMO

Usar datos ficticios.

Sugerido:

```text
2 sedes
6 especialidades
8 médicos
20 pacientes
agendas futuras
reservas en varios estados
```

---

# 60. CUENTAS DEMO

Roles:

```text
superadmin
administrador
recepcion
medico
paciente
auditor
```

Passwords solo en `.env`.

`.env.example` solo placeholders.

---

# 61. ARTISAN DEMO

Crear:

```text
php artisan clinic:seed-demo
```

Debe:

- leer credenciales de `.env`;
- generar hashes;
- llamar SP;
- asignar roles por SP;
- vincular médico/paciente;
- ser idempotente;
- no imprimir passwords.

---

# 62. REPOSITORIES

Crear:

```text
AuthRepository
UserRepository
PatientRepository
DoctorRepository
ScheduleRepository
ReservationRepository
AuditRepository
ReportRepository
```

Solo ellos conocen SQL.

---

# 63. STORED PROCEDURE EXECUTOR

Crear helper seguro.

Concepto:

```php
final class StoredProcedureExecutor
{
    public function select(string $procedure, array $params): array;
    public function command(string $procedure, array $params): ProcedureResult;
}
```

Los nombres de procedimiento deben ser constantes del código.

Nunca venir del request.

---

# 64. PARAMETRIZACIÓN

Correcto:

```php
DB::select(
    'EXEC api.usp_ReservaCrear ?, ?, ?, ?',
    [$userId, $patientId, $slotId, $key]
);
```

Incorrecto:

```php
DB::select("EXEC api.usp_ReservaCrear $slotId");
```

---

# 65. SERVICES

Crear:

```text
AuthService
PatientService
DoctorService
ScheduleService
ReservationService
AdminService
ReportService
```

Orquestan.

No duplican reglas críticas SQL.

---

# 66. DTO

Crear DTOs:

```text
AuthenticatedUserData
DoctorData
TimeSlotData
ReservationData
ProcedureResult
DashboardData
```

Evitar `stdClass` por todo el proyecto.

---

# 67. FORM REQUESTS

Ejemplos:

```text
LoginRequest
CreateReservationRequest
CancelReservationRequest
RescheduleReservationRequest
PatientRequest
DoctorRequest
ScheduleRequest
UserRequest
```

Validación de formato en Laravel.
Regla de negocio final en SQL.

---

# 68. CONTROLLERS

Deben ser delgados:

```text
Request
→ Service
→ Response
```

No SQL.
No transacciones.
No lógica pesada.

---

# 69. MIDDLEWARE / POLICIES

Crear autorización preventiva:

```text
auth
permission:reservas.gestionar
```

Pero SP vuelve a validar.

---

# 70. RUTAS

Agrupar:

```text
guest
auth
patient
doctor
reception
admin
auditor
```

Usar route names.

---

# 71. LOGIN UI

Pantalla profesional:

```text
Nexa Salud
Gestión de Citas Médicas
```

Campos:

```text
usuario/email
contraseña
mostrar/ocultar
```

Errores accesibles.

No mostrar credenciales demo.

---

# 72. DASHBOARD POR ROL

Paciente:

```text
próxima cita
citas futuras
historial
reservar
```

Médico:

```text
citas de hoy
agenda
atendidas
no asistieron
```

Recepción:

```text
citas del día
buscar paciente
nueva reserva
cancelaciones
```

Admin:

```text
citas
ocupación
médicos
cancelaciones
no-show
especialidades
```

Auditor:

```text
bitácora
reportes
```

---

# 73. RESERVA — WIZARD BLADE

Pasos:

```text
1 Especialidad
2 Médico/Sede
3 Fecha
4 Horario
5 Confirmación
```

Último submit usa idempotency UUID.

---

# 74. DIRECTORIO MÉDICOS

Cards:

```text
Nombre
CMP
Especialidad
Sede
Próxima disponibilidad
```

Filtros por:

```text
especialidad
sede
nombre
```

---

# 75. HORARIOS

Visual:

```text
Disponible
Bloqueado
No disponible
Seleccionado
```

No exponer horarios pasados.

---

# 76. CONFIRMACIÓN

Antes de reservar:

```text
Paciente
Especialidad
Médico
Sede
Consultorio
Fecha
Hora
Tipo
```

Después:

```text
Código de reserva
Estado
```

---

# 77. MIS CITAS

Tabs:

```text
Próximas
Historial
Canceladas
```

Acciones:

```text
Ver
Cancelar
Reprogramar
```

según reglas.

---

# 78. RECEPCIÓN

Operación rápida:

```text
Buscar paciente
Crear paciente
Ver disponibilidad
Crear reserva
Cancelar
Reprogramar
```

---

# 79. MÉDICO

Pantallas:

```text
Mi agenda
Hoy
Semana
Mis citas
```

Acciones:

```text
Atendida
No asistió
```

SP valida que la cita pertenezca al médico.

---

# 80. ADMIN MÉDICOS

```text
listar
crear
editar
activar/desactivar
especialidades
sedes
```

No delete físico con historial.

---

# 81. ADMIN AGENDA

```text
crear
generar rango
listar
bloquear
desbloquear
desactivar
```

Mostrar:

```text
total
disponibles
reservados
bloqueados
```

---

# 82. ADMIN USUARIOS / RBAC

```text
buscar
crear
activar/desactivar
asignar rol
ver permisos efectivos
```

Todo por SP.

---

# 83. ADMIN RESERVAS

Filtros:

```text
fecha
estado
médico
especialidad
sede
paciente
```

Paginación DB.

CSV opcional.

---

# 84. AUDITORÍA

Read-only.

Filtros:

```text
fecha
usuario
acción
entidad
resultado
correlation id
```

---

# 85. REPORTES

Pocos y útiles:

```text
reservas por especialidad
ocupación por médico
cancelaciones
no asistencia
```

Toda data desde SP.

---

# 86. BLADE COMPONENTS

Crear componentes como:

```text
<x-app-layout>
<x-auth-layout>
<x-alert>
<x-button>
<x-input>
<x-select>
<x-card>
<x-stat-card>
<x-badge>
<x-empty-state>
<x-table>
<x-pagination>
<x-modal>
<x-doctor-card>
<x-time-slot>
<x-reservation-status>
```

---

# 87. RESPONSIVE

Validar:

```text
375x812
768x1024
1024x768
1440x900
```

No overflow horizontal no controlado.

---

# 88. ACCESIBILIDAD

Aplicar:

```text
skip link
landmarks
labels
aria-live
focus visible
contraste AA
teclado
botones semánticos
reduced motion
```

---

# 89. SEGURIDAD WEB

Aplicar:

```text
CSRF
Blade escaping
rate limit login
session regeneration
cookies seguras según entorno
no mass assignment inseguro
no secretos en logs
mensajes de login genéricos
```

---

# 90. `.env`

No versionar.

Ejemplo:

```env
APP_NAME="Nexa Salud"
APP_ENV=local
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=sqlsrv
DB_HOST=localhost
DB_PORT=1433
DB_DATABASE=ReservasMedicasWeb
DB_USERNAME=
DB_PASSWORD=
DB_ENCRYPT=no
DB_TRUST_SERVER_CERTIFICATE=true

DEMO_SUPERADMIN_USER=superadmin
DEMO_SUPERADMIN_PASSWORD=change_me
```

`.env.example` sin secretos reales.

---

# 91. CACHE

Puede cachearse:

```text
especialidades
sedes
```

No cachear disponibilidad mucho tiempo.

---

# 92. SQL MASTER = SOURCE OF TRUTH

El dominio SQL debe tener una única fuente de verdad:

```text
database/sql/ReservasMedicasWeb_MASTER.sql
```

No duplicar todo en migrations Laravel.

Migrations solo para infraestructura del framework si fueran necesarias.

---

# 93. SCRIPTS POWERSHELL

Crear:

```text
scripts/check-environment.ps1
scripts/setup.ps1
scripts/start.ps1
scripts/test.ps1
scripts/db-validate.ps1
scripts/test-concurrency.ps1
```

No hacer operaciones destructivas por defecto.

---

# 94. START

Preferencia:

```text
php artisan serve
```

URL:

```text
http://127.0.0.1:8000
```

Usar Laragon virtual host solo si aporta.

---

# 95. FRONTEND BUILD

Debe pasar:

```powershell
npm run build
```

No depender de `npm run dev` para la entrega final.

---

# 96. TESTS PHP

Usar PHPUnit/Pest.

Probar:

```text
auth
middleware
permissions
routes
DTO mapping
services
views
errors
```

---

# 97. TESTS DE INTEGRACIÓN SQL

Casos obligatorios:

```text
reserva correcta
slot ocupado
paciente solapado
slot bloqueado
agenda inactiva
cita pasada
cancelación válida
cancelación fuera de política
reprogramación correcta
reprogramación fallida conserva original
idempotencia
sin permiso
médico intenta modificar cita ajena
```

---

# 98. TEST DE CONCURRENCIA

Ejecutar dos reservas simultáneas al mismo slot.

Resultado real esperado:

```text
1 éxito
1 rechazo
1 única reserva ocupante
```

Crear evidencia reproducible.

---

# 99. TEST IDEMPOTENCIA

Misma key dos veces:

```text
misma ReservaId
sin duplicado
```

---

# 100. QA ROLES

Validar todos:

```text
SUPERADMIN
ADMINISTRADOR
RECEPCIONISTA
MEDICO
PACIENTE
AUDITOR
```

Crear matriz en docs.

---

# 101. CORRELATION ID

Generar para requests sensibles.

Pasar a auditoría.

No usar para GET triviales si no aporta.

---

# 102. LOGGING

Laravel:

```text
errores técnicos
exceptions
correlation id
```

SQL:

```text
acciones de negocio
```

No duplicar indiscriminadamente.

---

# 103. PERFORMANCE

Evitar:

```text
SP por cada fila
N+1 lógico
dashboard con 20 llamadas
```

Usar SP consolidados.

---

# 104. DOCUMENTACIÓN

Crear:

```text
README.md
docs/ARQUITECTURA_LARAVEL.md
docs/MIGRACION_DESDE_VBNET.md
docs/DISENO_BASE_DATOS.md
docs/CATALOGO_STORED_PROCEDURES.md
docs/SEGURIDAD.md
docs/ROLES_PERMISOS.md
docs/FLUJOS_RESERVA.md
docs/QA_TESTING.md
docs/CUMPLIMIENTO_PRUEBA.md
docs/GUIA_DEMO_TECNICA.md
docs/BITACORA_CONSTRUCCION.md
docs/evidencias/CHECKLIST.md
```

---

# 105. MIGRACIÓN VB.NET → LARAVEL

Documentar mapa:

```text
FrmLogin
→ AuthController / Blade login

FrmPrincipal
→ Dashboard

FrmMedicos
→ Admin/Doctor module

FrmAgenda
→ Schedule module

FrmReservar
→ Appointment wizard

FrmMisReservas
→ Patient appointments

FrmReservasAdmin
→ Admin reservations
```

---

# 106. CATALOGO SP

Por SP documentar:

```text
Nombre
Propósito
Permiso
Parámetros
Resultado
Reglas
Transacción
Errores de negocio
Repository consumidor
```

---

# 107. BITÁCORA CONSTRUCCIÓN

Registrar:

```text
auditoría inicial
legacy
SQL V2
Laravel
drivers
repositories
services
auth
RBAC
UI
reservas
admin
testing
problemas
soluciones
QA
estado final
```

---

# 108. CUMPLIMIENTO PRUEBA

Tabla:

```text
Requisito | Implementación | Evidencia | Estado
```

Debe mencionar explícitamente:

```text
migración integral
Blade
rutas
controladores
modelos
vistas
documentación
```

---

# 109. GUIA DEMO

10–15 minutos:

```text
1 arquitectura
2 login
3 reserva paciente
4 concurrencia
5 reprogramación
6 recepción
7 médico
8 admin agenda
9 RBAC
10 auditoría
11 SQL/SP
12 tests
```

Agregar preguntas/respuestas probables.

---

# 110. NO CONVERTIR ESTO EN HIS

No agregar:

```text
historia clínica
farmacia
laboratorio
facturación
```

La prueba es reservas/citas.

---

# 111. `.gitignore`

Excluir:

```text
.env
/vendor
/node_modules
/public/build
/storage/logs/*
bootstrap/cache/*
*.log
backups
secrets
IDE files
```

Mantener:

```text
.env.example
composer.lock
package-lock.json
database/sql
docs
```

---

# 112. NO SECRETOS

Antes de commit revisar:

```powershell
git grep -n -i "password"
git grep -n -i "secret"
git grep -n -i "token"
git grep -n -i "api_key"
```

Analizar resultados.

---

# 113. NO CRUD DIRECTO — AUDITORÍA DE CÓDIGO

Antes de finalizar buscar:

```text
DB::table(
::create(
->save(
->update(
->delete(
Model::query(
```

Revisar que no exista bypass de SP para el dominio.

---

# 114. CODE STYLE

Aplicar:

```text
PSR-12
Laravel conventions
types
return types
Pint
```

Static analysis opcional si no distrae.

---

# 115. PLAN POR FASES

## Fase 1
Auditoría.

## Fase 2
Estudio legacy.

## Fase 3
Diseño SQL V2.

## Fase 4
Ejecutar/validar SQL.

## Fase 5
Base Laravel + SQL Server.

## Fase 6
Auth/RBAC.

## Fase 7
Pacientes/Médicos.

## Fase 8
Agenda.

## Fase 9
Reservas/Reprogramación.

## Fase 10
Recepción/Médico/Admin/Auditor.

## Fase 11
UI profesional.

## Fase 12
Tests/concurrencia/idempotencia.

## Fase 13
Documentación.

## Fase 14
QA final.

---

# 116. VALIDACIONES CRÍTICAS FINALES

Demostrar:

1. dos requests mismo slot;
2. paciente solapado;
3. médico agenda solapada;
4. consultorio solapado;
5. slot bloqueado;
6. agenda inactiva;
7. paciente inactivo;
8. médico inactivo;
9. reprogramación a slot ocupado;
10. cancelación fuera de política;
11. actor sin permiso;
12. key idempotencia repetida;
13. conflicto rowversion.

---

# 117. UX DE ERRORES

Mensajes claros:

```text
El horario acaba de ser reservado por otra persona.
No tienes permiso.
La cita ya fue cancelada.
No puedes cancelar con tan poca anticipación.
El registro fue modificado por otro usuario.
No existen horarios disponibles.
```

---

# 118. TRANSACCIONES SQL

Patrón:

```sql
SET XACT_ABORT ON;

BEGIN TRY
    BEGIN TRAN;

    -- validaciones y operación

    COMMIT;
END TRY
BEGIN CATCH
    IF XACT_STATE() <> 0
        ROLLBACK;

    -- respuesta/log seguro
END CATCH;
```

Aplicar donde corresponda.

---

# 119. NO MULTI-RESULT SET INNECESARIO

Para facilitar PDO_SQLSRV:

- un listado = un result set;
- un comando = una fila resultado;
- paginación = `COUNT(*) OVER()`.

---

# 120. DATOS DEMO SIN PRIVACIDAD REAL

No usar:

```text
DNI reales
teléfonos reales
emails personales
diagnósticos
```

---

# 121. SQL LEGACY NO SE DESTRUYE

Nueva BD sugerida:

```text
ReservasMedicasWeb
```

No borrar `ReservasMedicasDB`.

---

# 122. MASTER NO DESTRUCTIVO

`ReservasMedicasWeb_MASTER.sql` no debe:

```sql
DROP DATABASE
```

El reset DEV puede ser destructivo, separado y explícito.

---

# 123. SETUP ESPERADO

Idealmente:

```powershell
cd D:\Git-Hub\medical-appointments-laravel

composer install
npm install
Copy-Item .env.example .env
php artisan key:generate

# configurar SQL Server
# ejecutar database/sql/ReservasMedicasWeb_MASTER.sql

php artisan clinic:seed-demo

npm run build
php artisan serve
```

Ajustar al resultado real.

---

# 124. CRITERIO DE TERMINACIÓN

No declarar terminado hasta validar:

```text
Laravel inicia
SQL Server conecta
Blade renderiza
login funciona
roles funcionan
paciente reserva
doble reserva imposible
cancelación funciona
reprogramación funciona
recepción funciona
médico funciona
admin funciona
auditoría funciona
SP son autoridad
tests pasan
build pasa
responsive pasa
docs completas
```

---

# 125. STOP CONDITIONS

Pide confirmación solo si necesitas:

- borrar DB;
- perder datos;
- tocar instalación global riesgosa;
- sobrescribir legacy;
- usar credenciales desconocidas.

Para cambios reversibles normales, continúa.

---

# 126. PRIMERA RESPUESTA DE CLAUDE

Debes comenzar mostrando:

```text
AUDITORÍA INICIAL LARAVEL

Repositorio:
Proyecto legacy:
SQL legacy:
PHP:
Composer:
Node/npm:
SQL Server:
sqlsrv/pdo_sqlsrv:
Estado Git:
Riesgos:
Laravel propuesto:
BD nueva:
Arquitectura:
Plan por fases:
```

Luego continuar.

---

# 127. CIERRE OBLIGATORIO

Al finalizar:

```text
IMPLEMENTACIÓN LARAVEL COMPLETADA

1. Laravel/PHP
2. SQL Server
3. Migración desde VB.NET
4. Arquitectura
5. Blade
6. Roles/permisos
7. Pacientes
8. Médicos
9. Especialidades
10. Sedes/consultorios
11. Agenda
12. Reservas
13. Cancelación
14. Reprogramación
15. Concurrencia
16. Idempotencia
17. Auditoría
18. Reportes
19. Seguridad
20. Testing
21. QA responsive
22. Documentación
23. Archivos creados/modificados
24. Conteo real de objetos SQL
25. Commits locales
26. Pendientes manuales
27. Pasos para levantar
28. Guion de demo
```

No afirmar que algo fue verificado si no se verificó.

---

# 128. INSTRUCCIÓN DE INICIO PARA EL USUARIO

El usuario abrirá Claude Code con:

```powershell
cd D:\Git-Hub\medical-appointments-laravel
claude
```

Primer mensaje:

```text
Lee completamente PROMPT_CLAUDE_LARAVEL_MEDICAL_APPOINTMENTS.md ubicado en la raíz del repositorio y úsalo como especificación principal.

Primero realiza la AUDITORÍA INICIAL LARAVEL y estudia en modo solo lectura el proyecto VB.NET anterior y su script SQL.

Después continúa por fases. SQL Server debe ser la autoridad de las reglas críticas: Laravel debe consumir Stored Procedures, Views y Functions desde Repositories, sin CRUD directo sobre las tablas del dominio.

No modifiques ni borres el proyecto legacy, no destruyas bases existentes y no hagas git push.

Construye la solución hasta dejarla funcional, probada, documentada y visualmente profesional.
```
