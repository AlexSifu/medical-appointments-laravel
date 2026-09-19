# Catálogo de Stored Procedures (superficie `api`)

> Generado a partir de `database/sql/ReservasMedicasWeb_MASTER.sql` y de los repositorios `app/Repositories/SqlServer`.
> Laravel **solo** ejecuta objetos `api.usp_*` / `api.vw_*`; los nombres son constantes privadas de cada repositorio y
> `StoredProcedureExecutor` rechaza cualquier otro nombre. Los esquemas `seg`, `clin` y `audit` son internos.

## Convenciones

| Tipo | Resultado | Laravel |
|---|---|---|
| Comando | Una fila `Exito | Codigo | Mensaje | EntidadId` (vía `seg.usp_Resultado` / `seg.usp_Rechazo`) | `ProcedureResult`; si `Exito = 0` → `BusinessRuleException` con el `Codigo` |
| Consulta | Un result set | `select()` / `selectOne()` → DTO |
| Consulta paginada | Result set con `COUNT(*) OVER() AS TotalFilas` y `OFFSET/FETCH` | `PagedResult::fromRows()` |

Parámetros de contexto (`@Ip`, `@UserAgent`, `@CorrelationId`) los agrega `StoredProcedureExecutor::command()` desde `RequestContext`; nunca vienen del formulario.
Todo comando registra éxito **y** rechazo en `audit.BitacoraSistema` (acción, entidad, código, IP, correlation id). Los errores SQL inesperados se capturan con `seg.usp_ErrorCapturado` (código `ERROR_INTERNO`, sin detalles técnicos al usuario).

**Total:** 60 procedimientos `api.usp_*` y 16 vistas `api.vw_*`.

## Índice

- **Autenticación**: [`AuthObtenerUsuario`](#authobtenerusuario), [`AuthRegistrarFallo`](#authregistrarfallo), [`AuthRegistrarExito`](#authregistrarexito), [`AuthRegistrarLogout`](#authregistrarlogout), [`AuthObtenerPermisosUsuario`](#authobtenerpermisosusuario), [`UsuarioActualizarPasswordHash`](#usuarioactualizarpasswordhash), [`SistemaInicializarSuperadmin`](#sistemainicializarsuperadmin)
- **Usuarios y configuración**: [`AdminUsuariosBuscar`](#adminusuariosbuscar), [`AdminUsuarioObtener`](#adminusuarioobtener), [`AdminUsuarioCrear`](#adminusuariocrear), [`AdminUsuarioActualizar`](#adminusuarioactualizar), [`AdminUsuarioAsignarRol`](#adminusuarioasignarrol), [`AdminUsuarioVincularPerfil`](#adminusuariovincularperfil), [`AdminConfiguracionActualizar`](#adminconfiguracionactualizar)
- **Pacientes**: [`PacienteObtener`](#pacienteobtener), [`PacienteBuscar`](#pacientebuscar), [`PacienteActualizarPerfil`](#pacienteactualizarperfil), [`RecepcionPacienteCrear`](#recepcionpacientecrear), [`RecepcionPacienteActualizar`](#recepcionpacienteactualizar)
- **Médicos**: [`MedicosBuscar`](#medicosbuscar), [`MedicoObtener`](#medicoobtener), [`AdminMedicoCrear`](#adminmedicocrear), [`AdminMedicoActualizar`](#adminmedicoactualizar), [`AdminMedicoCambiarEstado`](#adminmedicocambiarestado), [`ReporteOcupacionMedicos`](#reporteocupacionmedicos)
- **Catálogos**: [`AdminMedicoAsignarEspecialidad`](#adminmedicoasignarespecialidad), [`AdminMedicoAsignarSede`](#adminmedicoasignarsede), [`AdminEspecialidadGuardar`](#adminespecialidadguardar), [`AdminSedeGuardar`](#adminsedeguardar), [`AdminConsultorioGuardar`](#adminconsultorioguardar), [`ReporteReservasPorEspecialidad`](#reportereservasporespecialidad)
- **Agenda**: [`AdminAgendaCrear`](#adminagendacrear), [`AdminAgendaGenerarRango`](#adminagendagenerarrango), [`AdminAgendaListar`](#adminagendalistar), [`AdminAgendaDesactivar`](#adminagendadesactivar), [`AdminHorarioBloquear`](#adminhorariobloquear), [`AdminHorarioDesbloquear`](#adminhorariodesbloquear), [`AdminBloqueosListar`](#adminbloqueoslistar), [`AgendaFechasDisponibles`](#agendafechasdisponibles), [`AgendaHorariosDisponibles`](#agendahorariosdisponibles), [`AgendaHorariosEstado`](#agendahorariosestado), [`AgendaObtenerDia`](#agendaobtenerdia), [`MedicoAgendaPropia`](#medicoagendapropia)
- **Reservas**: [`ReservaCrear`](#reservacrear), [`RecepcionReservaCrear`](#recepcionreservacrear), [`ReservaCancelar`](#reservacancelar), [`ReservaReprogramar`](#reservareprogramar), [`MedicoMarcarAtendida`](#medicomarcaratendida), [`MedicoMarcarNoAsistio`](#medicomarcarnoasistio), [`ReservaObtenerDetalle`](#reservaobtenerdetalle), [`ReservaHistorial`](#reservahistorial), [`ReservaMisReservas`](#reservamisreservas), [`AdminReservasBuscar`](#adminreservasbuscar), [`MedicoReservasDia`](#medicoreservasdia)
- **Auditoría**: [`AuditoriaBuscar`](#auditoriabuscar), [`AuditoriaAcciones`](#auditoriaacciones)
- **Panel y reportes**: [`DashboardResumen`](#dashboardresumen), [`DashboardSerieDiaria`](#dashboardseriediaria), [`ReporteCancelaciones`](#reportecancelaciones), [`ReporteNoAsistencia`](#reportenoasistencia)

## Autenticación

### AuthObtenerUsuario

| | |
|---|---|
| **Nombre** | `api.usp_AuthObtenerUsuario` |
| **Propósito** | Obtiene la cuenta candidata para login (hash, estado, bloqueo, perfil). |
| **Permiso** | ninguno (flujo de autenticación / bootstrap por consola) |
| **Parámetros** | `@Login` VARCHAR(150) |
| **Resultado** | Result set |
| **Reglas / transacción** | solo lectura |
| **Errores de negocio** | — |
| **Repository consumidor** | `SqlServerAuthRepository::findForLogin()` |

### AuthRegistrarFallo

| | |
|---|---|
| **Nombre** | `api.usp_AuthRegistrarFallo` |
| **Propósito** | Registra un intento fallido; aplica bloqueo temporal tras N fallos. |
| **Permiso** | ninguno (flujo de autenticación / bootstrap por consola) |
| **Parámetros** | `@UsuarioId` INT (opcional)<br>`@Login` VARCHAR(150)<br>`@Motivo` VARCHAR(20) |
| **Resultado** | Fila estándar `Exito|Codigo|Mensaje|EntidadId` |
| **Reglas / transacción** | sentencia única (atómica) |
| **Errores de negocio** | — |
| **Repository consumidor** | `SqlServerAuthRepository::registerFailure()` |

### AuthRegistrarExito

| | |
|---|---|
| **Nombre** | `api.usp_AuthRegistrarExito` |
| **Propósito** | Registra login correcto; reinicia contador de fallos y fija último acceso. |
| **Permiso** | ninguno (flujo de autenticación / bootstrap por consola) |
| **Parámetros** | `@UsuarioId` INT |
| **Resultado** | Fila estándar `Exito|Codigo|Mensaje|EntidadId` |
| **Reglas / transacción** | sentencia única (atómica) |
| **Errores de negocio** | — |
| **Repository consumidor** | `SqlServerAuthRepository::registerSuccess()` |

### AuthRegistrarLogout

| | |
|---|---|
| **Nombre** | `api.usp_AuthRegistrarLogout` |
| **Propósito** | Registra el cierre de sesión en la bitácora. |
| **Permiso** | ninguno (flujo de autenticación / bootstrap por consola) |
| **Parámetros** | `@UsuarioId` INT |
| **Resultado** | Fila estándar `Exito|Codigo|Mensaje|EntidadId` |
| **Reglas / transacción** | sentencia única (atómica) |
| **Errores de negocio** | — |
| **Repository consumidor** | `SqlServerAuthRepository::registerLogout()` |

### AuthObtenerPermisosUsuario

| | |
|---|---|
| **Nombre** | `api.usp_AuthObtenerPermisosUsuario` |
| **Propósito** | Roles y permisos efectivos del usuario (se releen cada 5 min). |
| **Permiso** | ninguno (flujo de autenticación / bootstrap por consola) |
| **Parámetros** | `@UsuarioId` INT |
| **Resultado** | Result set |
| **Reglas / transacción** | solo lectura |
| **Errores de negocio** | — |
| **Repository consumidor** | `SqlServerAuthRepository::grants()`, `SqlServerUserRepository::effectiveGrants()` |

### UsuarioActualizarPasswordHash

| | |
|---|---|
| **Nombre** | `api.usp_UsuarioActualizarPasswordHash` |
| **Propósito** | Reemplaza el hash de contraseña (rehash legacy→bcrypt, reset por admin). |
| **Permiso** | `usuarios.editar` |
| **Parámetros** | `@ActorUsuarioId` INT<br>`@UsuarioId` INT<br>`@PasswordHash` NVARCHAR(512)<br>`@PasswordAlgoritmo` VARCHAR(20) |
| **Resultado** | Fila estándar `Exito|Codigo|Mensaje|EntidadId` |
| **Reglas / transacción** | sentencia única (atómica) |
| **Errores de negocio** | `SIN_PERMISO` |
| **Repository consumidor** | `SqlServerAuthRepository::updatePasswordHash()` |

### SistemaInicializarSuperadmin

| | |
|---|---|
| **Nombre** | `api.usp_SistemaInicializarSuperadmin` |
| **Propósito** | Crea el primer SUPERADMIN solo si no existe ninguno activo (bootstrap). |
| **Permiso** | ninguno (flujo de autenticación / bootstrap por consola) |
| **Parámetros** | `@NombreUsuario` VARCHAR(50)<br>`@Email` VARCHAR(150)<br>`@Nombres` NVARCHAR(80)<br>`@Apellidos` NVARCHAR(100)<br>`@PasswordHash` NVARCHAR(512)<br>`@PasswordAlgoritmo` VARCHAR(20) |
| **Resultado** | Fila estándar `Exito|Codigo|Mensaje|EntidadId` |
| **Reglas / transacción** | transacción explícita + `XACT_ABORT` |
| **Errores de negocio** | — |
| **Repository consumidor** | `SqlServerUserRepository::bootstrapSuperadmin()` |

## Usuarios y configuración

### AdminUsuariosBuscar

| | |
|---|---|
| **Nombre** | `api.usp_AdminUsuariosBuscar` |
| **Propósito** | Listado paginado de usuarios con filtros. |
| **Permiso** | `usuarios.ver` |
| **Parámetros** | `@ActorUsuarioId` INT<br>`@Texto` NVARCHAR(100) (opcional)<br>`@RolCodigo` VARCHAR(30) (opcional)<br>`@Activo` BIT (opcional)<br>`@Pagina` INT (opcional)<br>`@TamanoPagina` INT (opcional) |
| **Resultado** | Result set paginado (`TotalFilas`) |
| **Reglas / transacción** | solo lectura |
| **Errores de negocio** | `SIN_PERMISO (error 50403 → 403)` |
| **Repository consumidor** | `SqlServerUserRepository::search()` |

### AdminUsuarioObtener

| | |
|---|---|
| **Nombre** | `api.usp_AdminUsuarioObtener` |
| **Propósito** | Detalle de un usuario con roles y perfil vinculado. |
| **Permiso** | `usuarios.ver` |
| **Parámetros** | `@ActorUsuarioId` INT<br>`@UsuarioId` INT |
| **Resultado** | Result set |
| **Reglas / transacción** | solo lectura |
| **Errores de negocio** | `SIN_PERMISO (error 50403 → 403)` |
| **Repository consumidor** | `SqlServerUserRepository::find()` |

### AdminUsuarioCrear

| | |
|---|---|
| **Nombre** | `api.usp_AdminUsuarioCrear` |
| **Propósito** | Crea un usuario con rol inicial. |
| **Permiso** | `usuarios.crear` |
| **Parámetros** | `@ActorUsuarioId` INT<br>`@NombreUsuario` VARCHAR(50)<br>`@Email` VARCHAR(150)<br>`@Nombres` NVARCHAR(80)<br>`@Apellidos` NVARCHAR(100)<br>`@PasswordHash` NVARCHAR(512)<br>`@PasswordAlgoritmo` VARCHAR(20)<br>`@RolCodigo` VARCHAR(30)<br>`@MedicoId` INT (opcional)<br>`@PacienteId` INT (opcional) |
| **Resultado** | Fila estándar `Exito|Codigo|Mensaje|EntidadId` — éxito: `USUARIO_CREADO` |
| **Reglas / transacción** | transacción explícita + `XACT_ABORT`; locks `UPDLOCK, HOLDLOCK` en orden fijo (slot → paciente) |
| **Errores de negocio** | `SIN_PERMISO` |
| **Repository consumidor** | `SqlServerUserRepository::create()` |

### AdminUsuarioActualizar

| | |
|---|---|
| **Nombre** | `api.usp_AdminUsuarioActualizar` |
| **Propósito** | Actualiza datos/estado de un usuario (control optimista). |
| **Permiso** | `usuarios.editar` |
| **Parámetros** | `@ActorUsuarioId` INT<br>`@UsuarioId` INT<br>`@Email` VARCHAR(150)<br>`@Nombres` NVARCHAR(80)<br>`@Apellidos` NVARCHAR(100)<br>`@Activo` BIT<br>`@VersionFila` VARCHAR(18) |
| **Resultado** | Fila estándar `Exito|Codigo|Mensaje|EntidadId` — éxito: `USUARIO_ACTUALIZADO` |
| **Reglas / transacción** | transacción explícita + `XACT_ABORT`; locks `UPDLOCK, HOLDLOCK` en orden fijo (slot → paciente); control optimista por `rowversion` |
| **Errores de negocio** | `SIN_PERMISO` |
| **Repository consumidor** | `SqlServerUserRepository::update()` |

### AdminUsuarioAsignarRol

| | |
|---|---|
| **Nombre** | `api.usp_AdminUsuarioAsignarRol` |
| **Propósito** | Asigna o retira un rol (protege al último SUPERADMIN). |
| **Permiso** | `usuarios.roles` |
| **Parámetros** | `@ActorUsuarioId` INT<br>`@UsuarioId` INT<br>`@RolCodigo` VARCHAR(30)<br>`@Asignar` BIT |
| **Resultado** | Fila estándar `Exito|Codigo|Mensaje|EntidadId` |
| **Reglas / transacción** | transacción explícita + `XACT_ABORT` |
| **Errores de negocio** | `SIN_PERMISO` |
| **Repository consumidor** | `SqlServerUserRepository::assignRole()` |

### AdminUsuarioVincularPerfil

| | |
|---|---|
| **Nombre** | `api.usp_AdminUsuarioVincularPerfil` |
| **Propósito** | Vincula el usuario a una ficha de médico o paciente. |
| **Permiso** | `usuarios.editar` |
| **Parámetros** | `@ActorUsuarioId` INT<br>`@UsuarioId` INT<br>`@MedicoId` INT (opcional)<br>`@PacienteId` INT (opcional) |
| **Resultado** | Fila estándar `Exito|Codigo|Mensaje|EntidadId` — éxito: `USUARIO_VINCULADO` |
| **Reglas / transacción** | transacción explícita + `XACT_ABORT`; locks `UPDLOCK, HOLDLOCK` en orden fijo (slot → paciente) |
| **Errores de negocio** | `SIN_PERMISO` |
| **Repository consumidor** | `SqlServerUserRepository::linkProfile()` |

### AdminConfiguracionActualizar

| | |
|---|---|
| **Nombre** | `api.usp_AdminConfiguracionActualizar` |
| **Propósito** | Modifica un parámetro de configuración de negocio. |
| **Permiso** | `configuracion.gestionar` |
| **Parámetros** | `@ActorUsuarioId` INT<br>`@Clave` VARCHAR(60)<br>`@Valor` NVARCHAR(200) |
| **Resultado** | Fila estándar `Exito|Codigo|Mensaje|EntidadId` — éxito: `CONFIGURACION_ACTUALIZADA` |
| **Reglas / transacción** | sentencia única (atómica) |
| **Errores de negocio** | `SIN_PERMISO` |
| **Repository consumidor** | `SqlServerCatalogRepository::updateConfiguration()` |

## Pacientes

### PacienteObtener

| | |
|---|---|
| **Nombre** | `api.usp_PacienteObtener` |
| **Propósito** | Ficha de paciente (propia o, con permiso, de cualquiera). |
| **Permiso** | `pacientes.ver` |
| **Parámetros** | `@ActorUsuarioId` INT<br>`@PacienteId` INT (opcional) |
| **Resultado** | Result set |
| **Reglas / transacción** | solo lectura |
| **Errores de negocio** | — |
| **Repository consumidor** | `SqlServerPatientRepository::find()` |

### PacienteBuscar

| | |
|---|---|
| **Nombre** | `api.usp_PacienteBuscar` |
| **Propósito** | Búsqueda paginada de pacientes. |
| **Permiso** | `pacientes.ver` |
| **Parámetros** | `@ActorUsuarioId` INT<br>`@Texto` NVARCHAR(100) (opcional)<br>`@Activo` BIT (opcional)<br>`@Pagina` INT (opcional)<br>`@TamanoPagina` INT (opcional) |
| **Resultado** | Result set paginado (`TotalFilas`) |
| **Reglas / transacción** | solo lectura |
| **Errores de negocio** | `SIN_PERMISO (error 50403 → 403)` |
| **Repository consumidor** | `SqlServerPatientRepository::search()` |

### PacienteActualizarPerfil

| | |
|---|---|
| **Nombre** | `api.usp_PacienteActualizarPerfil` |
| **Propósito** | El paciente actualiza sus datos de contacto. |
| **Permiso** | autenticado |
| **Parámetros** | `@ActorUsuarioId` INT<br>`@Telefono` VARCHAR(20) (opcional)<br>`@Email` VARCHAR(150) (opcional)<br>`@Direccion` NVARCHAR(200) (opcional)<br>`@ContactoEmergencia` NVARCHAR(150) (opcional)<br>`@VersionFila` VARCHAR(18) |
| **Resultado** | Fila estándar `Exito|Codigo|Mensaje|EntidadId` — éxito: `PACIENTE_PERFIL_ACTUALIZADO` |
| **Reglas / transacción** | transacción explícita + `XACT_ABORT`; locks `UPDLOCK, HOLDLOCK` en orden fijo (slot → paciente); control optimista por `rowversion` |
| **Errores de negocio** | `CONFLICTO_EDICION`, `EMAIL_INVALIDO`, `SIN_PERMISO` |
| **Repository consumidor** | `SqlServerPatientRepository::updateOwnProfile()` |

### RecepcionPacienteCrear

| | |
|---|---|
| **Nombre** | `api.usp_RecepcionPacienteCrear` |
| **Propósito** | Recepción registra un paciente (documento único). |
| **Permiso** | `pacientes.crear` |
| **Parámetros** | `@ActorUsuarioId` INT<br>`@TipoDocumento` VARCHAR(10)<br>`@NumeroDocumento` VARCHAR(20)<br>`@Nombres` NVARCHAR(80)<br>`@Apellidos` NVARCHAR(100)<br>`@FechaNacimiento` DATE<br>`@Sexo` CHAR(1) (opcional)<br>`@Telefono` VARCHAR(20) (opcional)<br>`@Email` VARCHAR(150) (opcional)<br>`@Direccion` NVARCHAR(200) (opcional)<br>`@ContactoEmergencia` NVARCHAR(150) (opcional) |
| **Resultado** | Fila estándar `Exito|Codigo|Mensaje|EntidadId` — éxito: `PACIENTE_CREADO` |
| **Reglas / transacción** | transacción explícita + `XACT_ABORT`; locks `UPDLOCK, HOLDLOCK` en orden fijo (slot → paciente) |
| **Errores de negocio** | `DATOS_INVALIDOS`, `DOCUMENTO_DUPLICADO`, `DOCUMENTO_INVALIDO`, `EMAIL_INVALIDO`, `FECHA_NACIMIENTO_INVALIDA`, `SIN_PERMISO` |
| **Repository consumidor** | `SqlServerPatientRepository::create()` |

### RecepcionPacienteActualizar

| | |
|---|---|
| **Nombre** | `api.usp_RecepcionPacienteActualizar` |
| **Propósito** | Recepción actualiza un paciente (control optimista). |
| **Permiso** | `pacientes.editar` |
| **Parámetros** | `@ActorUsuarioId` INT<br>`@PacienteId` INT<br>`@TipoDocumento` VARCHAR(10)<br>`@NumeroDocumento` VARCHAR(20)<br>`@Nombres` NVARCHAR(80)<br>`@Apellidos` NVARCHAR(100)<br>`@FechaNacimiento` DATE<br>`@Sexo` CHAR(1) (opcional)<br>`@Telefono` VARCHAR(20) (opcional)<br>`@Email` VARCHAR(150) (opcional)<br>`@Direccion` NVARCHAR(200) (opcional)<br>`@ContactoEmergencia` NVARCHAR(150) (opcional)<br>`@Activo` BIT (opcional)<br>`@VersionFila` VARCHAR(18) |
| **Resultado** | Fila estándar `Exito|Codigo|Mensaje|EntidadId` — éxito: `PACIENTE_ACTUALIZADO` |
| **Reglas / transacción** | transacción explícita + `XACT_ABORT`; locks `UPDLOCK, HOLDLOCK` en orden fijo (slot → paciente); control optimista por `rowversion` |
| **Errores de negocio** | `CONFLICTO_EDICION`, `DATOS_INVALIDOS`, `DOCUMENTO_DUPLICADO`, `DOCUMENTO_INVALIDO`, `EMAIL_INVALIDO`, `FECHA_NACIMIENTO_INVALIDA`, `NO_ENCONTRADO`, `RESERVAS_FUTURAS`, `SIN_PERMISO` |
| **Repository consumidor** | `SqlServerPatientRepository::update()` |

## Médicos

### MedicosBuscar

| | |
|---|---|
| **Nombre** | `api.usp_MedicosBuscar` |
| **Propósito** | Directorio paginado de médicos con próxima fecha disponible. |
| **Permiso** | `medicos.ver`, `medicos.editar` (según el caso) |
| **Parámetros** | `@ActorUsuarioId` INT<br>`@Texto` NVARCHAR(100) (opcional)<br>`@EspecialidadId` INT (opcional)<br>`@SedeId` INT (opcional)<br>`@Activo` BIT (opcional)<br>`@Pagina` INT (opcional)<br>`@TamanoPagina` INT (opcional) |
| **Resultado** | Result set paginado (`TotalFilas`) |
| **Reglas / transacción** | solo lectura |
| **Errores de negocio** | `SIN_PERMISO (error 50403 → 403)` |
| **Repository consumidor** | `SqlServerDoctorRepository::search()` |

### MedicoObtener

| | |
|---|---|
| **Nombre** | `api.usp_MedicoObtener` |
| **Propósito** | Detalle de un médico con especialidades y sedes. |
| **Permiso** | `medicos.ver`, `medicos.editar` (según el caso) |
| **Parámetros** | `@ActorUsuarioId` INT<br>`@MedicoId` INT (opcional) |
| **Resultado** | Result set |
| **Reglas / transacción** | solo lectura |
| **Errores de negocio** | — |
| **Repository consumidor** | `SqlServerDoctorRepository::find()` |

### AdminMedicoCrear

| | |
|---|---|
| **Nombre** | `api.usp_AdminMedicoCrear` |
| **Propósito** | Registra un médico (CMP único). |
| **Permiso** | `medicos.crear` |
| **Parámetros** | `@ActorUsuarioId` INT<br>`@CMP` VARCHAR(20)<br>`@Nombres` NVARCHAR(80)<br>`@Apellidos` NVARCHAR(100)<br>`@Telefono` VARCHAR(20) (opcional)<br>`@Email` VARCHAR(150) (opcional)<br>`@EspecialidadId` INT<br>`@SedeId` INT |
| **Resultado** | Fila estándar `Exito|Codigo|Mensaje|EntidadId` — éxito: `MEDICO_CREADO` |
| **Reglas / transacción** | transacción explícita + `XACT_ABORT`; locks `UPDLOCK, HOLDLOCK` en orden fijo (slot → paciente) |
| **Errores de negocio** | `CMP_DUPLICADO`, `CMP_INVALIDO`, `DATOS_INVALIDOS`, `EMAIL_INVALIDO`, `ESPECIALIDAD_INACTIVA`, `SEDE_INACTIVA`, `SIN_PERMISO` |
| **Repository consumidor** | `SqlServerDoctorRepository::create()` |

### AdminMedicoActualizar

| | |
|---|---|
| **Nombre** | `api.usp_AdminMedicoActualizar` |
| **Propósito** | Actualiza datos de un médico (control optimista). |
| **Permiso** | `medicos.editar` |
| **Parámetros** | `@ActorUsuarioId` INT<br>`@MedicoId` INT<br>`@CMP` VARCHAR(20)<br>`@Nombres` NVARCHAR(80)<br>`@Apellidos` NVARCHAR(100)<br>`@Telefono` VARCHAR(20) (opcional)<br>`@Email` VARCHAR(150) (opcional)<br>`@VersionFila` VARCHAR(18) |
| **Resultado** | Fila estándar `Exito|Codigo|Mensaje|EntidadId` — éxito: `MEDICO_ACTUALIZADO` |
| **Reglas / transacción** | transacción explícita + `XACT_ABORT`; locks `UPDLOCK, HOLDLOCK` en orden fijo (slot → paciente); control optimista por `rowversion` |
| **Errores de negocio** | `CMP_DUPLICADO`, `CMP_INVALIDO`, `CONFLICTO_EDICION`, `DATOS_INVALIDOS`, `EMAIL_INVALIDO`, `NO_ENCONTRADO`, `SIN_PERMISO` |
| **Repository consumidor** | `SqlServerDoctorRepository::update()` |

### AdminMedicoCambiarEstado

| | |
|---|---|
| **Nombre** | `api.usp_AdminMedicoCambiarEstado` |
| **Propósito** | Activa/desactiva un médico (bloquea si tiene citas futuras). |
| **Permiso** | `medicos.editar` |
| **Parámetros** | `@ActorUsuarioId` INT<br>`@MedicoId` INT<br>`@Activo` BIT<br>`@VersionFila` VARCHAR(18) |
| **Resultado** | Fila estándar `Exito|Codigo|Mensaje|EntidadId` |
| **Reglas / transacción** | transacción explícita + `XACT_ABORT`; locks `UPDLOCK, HOLDLOCK` en orden fijo (slot → paciente); control optimista por `rowversion` |
| **Errores de negocio** | `CONFLICTO_EDICION`, `NO_ENCONTRADO`, `RESERVAS_FUTURAS`, `SIN_PERMISO` |
| **Repository consumidor** | `SqlServerDoctorRepository::changeStatus()` |

### ReporteOcupacionMedicos

| | |
|---|---|
| **Nombre** | `api.usp_ReporteOcupacionMedicos` |
| **Propósito** | Reporte: ocupación de horarios por médico. |
| **Permiso** | autenticado |
| **Parámetros** | `@ActorUsuarioId` INT<br>`@FechaDesde` DATE (opcional)<br>`@FechaHasta` DATE (opcional)<br>`@EspecialidadId` INT (opcional)<br>`@SedeId` INT (opcional) |
| **Resultado** | Result set |
| **Reglas / transacción** | solo lectura |
| **Errores de negocio** | — |
| **Repository consumidor** | `SqlServerReportRepository::doctorOccupancy()` |

## Catálogos

### AdminMedicoAsignarEspecialidad

| | |
|---|---|
| **Nombre** | `api.usp_AdminMedicoAsignarEspecialidad` |
| **Propósito** | Asigna o retira una especialidad al médico. |
| **Permiso** | `medicos.editar` |
| **Parámetros** | `@ActorUsuarioId` INT<br>`@MedicoId` INT<br>`@EspecialidadId` INT<br>`@Asignar` BIT (opcional)<br>`@EsPrincipal` BIT (opcional) |
| **Resultado** | Fila estándar `Exito|Codigo|Mensaje|EntidadId` |
| **Reglas / transacción** | transacción explícita + `XACT_ABORT` |
| **Errores de negocio** | `AGENDAS_FUTURAS`, `ESPECIALIDAD_INACTIVA`, `NO_ENCONTRADO`, `SIN_PERMISO`, `ULTIMA_ESPECIALIDAD` |
| **Repository consumidor** | `SqlServerDoctorRepository::assignSpecialty()` |

### AdminMedicoAsignarSede

| | |
|---|---|
| **Nombre** | `api.usp_AdminMedicoAsignarSede` |
| **Propósito** | Asigna o retira una sede al médico. |
| **Permiso** | `medicos.editar` |
| **Parámetros** | `@ActorUsuarioId` INT<br>`@MedicoId` INT<br>`@SedeId` INT<br>`@Asignar` BIT (opcional) |
| **Resultado** | Fila estándar `Exito|Codigo|Mensaje|EntidadId` |
| **Reglas / transacción** | transacción explícita + `XACT_ABORT` |
| **Errores de negocio** | `AGENDAS_FUTURAS`, `NO_ENCONTRADO`, `SEDE_INACTIVA`, `SIN_PERMISO`, `ULTIMA_SEDE` |
| **Repository consumidor** | `SqlServerDoctorRepository::assignBranch()` |

### AdminEspecialidadGuardar

| | |
|---|---|
| **Nombre** | `api.usp_AdminEspecialidadGuardar` |
| **Propósito** | Crea o actualiza una especialidad. |
| **Permiso** | `especialidades.gestionar` |
| **Parámetros** | `@ActorUsuarioId` INT<br>`@EspecialidadId` INT (opcional)<br>`@Nombre` NVARCHAR(100)<br>`@Descripcion` NVARCHAR(250) (opcional)<br>`@Activo` BIT (opcional) |
| **Resultado** | Fila estándar `Exito|Codigo|Mensaje|EntidadId` |
| **Reglas / transacción** | transacción explícita + `XACT_ABORT`; locks `UPDLOCK, HOLDLOCK` en orden fijo (slot → paciente) |
| **Errores de negocio** | `AGENDAS_FUTURAS`, `DATOS_INVALIDOS`, `NOMBRE_DUPLICADO`, `NO_ENCONTRADO`, `SIN_PERMISO` |
| **Repository consumidor** | `SqlServerCatalogRepository::saveSpecialty()` |

### AdminSedeGuardar

| | |
|---|---|
| **Nombre** | `api.usp_AdminSedeGuardar` |
| **Propósito** | Crea o actualiza una sede. |
| **Permiso** | `sedes.gestionar` |
| **Parámetros** | `@ActorUsuarioId` INT<br>`@SedeId` INT (opcional)<br>`@Codigo` VARCHAR(20)<br>`@Nombre` NVARCHAR(100)<br>`@Direccion` NVARCHAR(200) (opcional)<br>`@Activo` BIT (opcional) |
| **Resultado** | Fila estándar `Exito|Codigo|Mensaje|EntidadId` |
| **Reglas / transacción** | transacción explícita + `XACT_ABORT`; locks `UPDLOCK, HOLDLOCK` en orden fijo (slot → paciente) |
| **Errores de negocio** | `AGENDAS_FUTURAS`, `DATOS_INVALIDOS`, `NOMBRE_DUPLICADO`, `NO_ENCONTRADO`, `SIN_PERMISO` |
| **Repository consumidor** | `SqlServerCatalogRepository::saveBranch()` |

### AdminConsultorioGuardar

| | |
|---|---|
| **Nombre** | `api.usp_AdminConsultorioGuardar` |
| **Propósito** | Crea o actualiza un consultorio de una sede. |
| **Permiso** | `consultorios.gestionar` |
| **Parámetros** | `@ActorUsuarioId` INT<br>`@ConsultorioId` INT (opcional)<br>`@SedeId` INT<br>`@Codigo` VARCHAR(20)<br>`@Nombre` NVARCHAR(100)<br>`@Piso` TINYINT (opcional)<br>`@Activo` BIT (opcional) |
| **Resultado** | Fila estándar `Exito|Codigo|Mensaje|EntidadId` |
| **Reglas / transacción** | transacción explícita + `XACT_ABORT`; locks `UPDLOCK, HOLDLOCK` en orden fijo (slot → paciente) |
| **Errores de negocio** | `AGENDAS_FUTURAS`, `CODIGO_DUPLICADO`, `DATOS_INVALIDOS`, `NO_ENCONTRADO`, `OPERACION_NO_PERMITIDA`, `SEDE_INACTIVA`, `SIN_PERMISO` |
| **Repository consumidor** | `SqlServerCatalogRepository::saveRoom()` |

### ReporteReservasPorEspecialidad

| | |
|---|---|
| **Nombre** | `api.usp_ReporteReservasPorEspecialidad` |
| **Propósito** | Reporte: reservas por especialidad y estado. |
| **Permiso** | autenticado |
| **Parámetros** | `@ActorUsuarioId` INT<br>`@FechaDesde` DATE (opcional)<br>`@FechaHasta` DATE (opcional)<br>`@SedeId` INT (opcional) |
| **Resultado** | Result set |
| **Reglas / transacción** | solo lectura |
| **Errores de negocio** | — |
| **Repository consumidor** | `SqlServerReportRepository::reservationsBySpecialty()` |

## Agenda

### AdminAgendaCrear

| | |
|---|---|
| **Nombre** | `api.usp_AdminAgendaCrear` |
| **Propósito** | Crea una agenda diaria y genera sus horarios (sin solapes). |
| **Permiso** | `agenda.gestionar` |
| **Parámetros** | `@ActorUsuarioId` INT<br>`@MedicoId` INT<br>`@EspecialidadId` INT<br>`@SedeId` INT<br>`@ConsultorioId` INT<br>`@TipoAtencionId` TINYINT<br>`@Fecha` DATE<br>`@HoraInicio` TIME(0)<br>`@HoraFin` TIME(0)<br>`@DuracionMinutos` SMALLINT |
| **Resultado** | Fila estándar `Exito|Codigo|Mensaje|EntidadId` — éxito: `AGENDA_CREADA` |
| **Reglas / transacción** | transacción explícita + `XACT_ABORT`; aislamiento SERIALIZABLE |
| **Errores de negocio** | `AGENDA_EXISTENTE`, `CONSULTORIO_INVALIDO`, `CONSULTORIO_SOLAPADO`, `DURACION_INVALIDA`, `ESPECIALIDAD_NO_ASIGNADA`, `FECHA_PASADA`, `FUERA_DE_RANGO`, `HORARIO_INVALIDO`, `MEDICO_AGENDA_SOLAPADA`, `MEDICO_INACTIVO`, `SEDE_NO_ASIGNADA`, `SIN_PERMISO`, `TIPO_ATENCION_INVALIDO` |
| **Repository consumidor** | `SqlServerScheduleRepository::createAgenda()` |

### AdminAgendaGenerarRango

| | |
|---|---|
| **Nombre** | `api.usp_AdminAgendaGenerarRango` |
| **Propósito** | Genera agendas para un rango de fechas y días de semana (máx. 92 días). |
| **Permiso** | `agenda.gestionar` |
| **Parámetros** | `@ActorUsuarioId` INT<br>`@MedicoId` INT<br>`@EspecialidadId` INT<br>`@SedeId` INT<br>`@ConsultorioId` INT<br>`@TipoAtencionId` TINYINT<br>`@FechaDesde` DATE<br>`@FechaHasta` DATE<br>`@DiasSemana` VARCHAR(20)<br>`@HoraInicio` TIME(0)<br>`@HoraFin` TIME(0)<br>`@DuracionMinutos` SMALLINT |
| **Resultado** | Fila estándar `Exito|Codigo|Mensaje|EntidadId` — éxito: `AGENDA_GENERADA_RANGO` |
| **Reglas / transacción** | transacción explícita + `XACT_ABORT`; aislamiento SERIALIZABLE |
| **Errores de negocio** | `AGENDA_EXISTENTE`, `CONSULTORIO_INVALIDO`, `CONSULTORIO_SOLAPADO`, `DIAS_INVALIDOS`, `DURACION_INVALIDA`, `ESPECIALIDAD_NO_ASIGNADA`, `FECHA_PASADA`, `FUERA_DE_RANGO`, `HORARIO_INVALIDO`, `MEDICO_AGENDA_SOLAPADA`, `MEDICO_INACTIVO`, `RANGO_INVALIDO`, `SEDE_NO_ASIGNADA`, `SIN_FECHAS`, `SIN_PERMISO`, `TIPO_ATENCION_INVALIDO` |
| **Repository consumidor** | `SqlServerScheduleRepository::generateRange()` |

### AdminAgendaListar

| | |
|---|---|
| **Nombre** | `api.usp_AdminAgendaListar` |
| **Propósito** | Listado paginado de agendas. |
| **Permiso** | `agenda.ver`, `agenda.gestionar`, `reservas.gestionar` (según el caso) |
| **Parámetros** | `@ActorUsuarioId` INT<br>`@MedicoId` INT (opcional)<br>`@EspecialidadId` INT (opcional)<br>`@SedeId` INT (opcional)<br>`@FechaDesde` DATE (opcional)<br>`@FechaHasta` DATE (opcional)<br>`@Activo` BIT (opcional)<br>`@Pagina` INT (opcional)<br>`@TamanoPagina` INT (opcional) |
| **Resultado** | Result set paginado (`TotalFilas`) |
| **Reglas / transacción** | solo lectura |
| **Errores de negocio** | `SIN_PERMISO (error 50403 → 403)` |
| **Repository consumidor** | `SqlServerScheduleRepository::listAgendas()` |

### AdminAgendaDesactivar

| | |
|---|---|
| **Nombre** | `api.usp_AdminAgendaDesactivar` |
| **Propósito** | Desactiva una agenda sin reservas activas. |
| **Permiso** | `agenda.gestionar` |
| **Parámetros** | `@ActorUsuarioId` INT<br>`@AgendaId` BIGINT<br>`@VersionFila` VARCHAR(18) |
| **Resultado** | Fila estándar `Exito|Codigo|Mensaje|EntidadId` — éxito: `AGENDA_DESACTIVADA` |
| **Reglas / transacción** | transacción explícita + `XACT_ABORT`; aislamiento SERIALIZABLE; locks `UPDLOCK, HOLDLOCK` en orden fijo (slot → paciente); control optimista por `rowversion` |
| **Errores de negocio** | `AGENDA_INACTIVA`, `CONFLICTO_EDICION`, `NO_ENCONTRADO`, `RESERVAS_EN_AGENDA`, `SIN_PERMISO` |
| **Repository consumidor** | `SqlServerScheduleRepository::deactivateAgenda()` |

### AdminHorarioBloquear

| | |
|---|---|
| **Nombre** | `api.usp_AdminHorarioBloquear` |
| **Propósito** | Bloquea un rango horario del médico (rechaza si hay reservas confirmadas en el intervalo). |
| **Permiso** | `agenda.bloquear` |
| **Parámetros** | `@ActorUsuarioId` INT<br>`@MedicoId` INT (opcional)<br>`@Fecha` DATE (opcional)<br>`@HoraInicio` TIME(0) (opcional)<br>`@HoraFin` TIME(0) (opcional)<br>`@TipoBloqueo` VARCHAR(20) (opcional)<br>`@Motivo` NVARCHAR(200)<br>`@HorarioMedicoId` BIGINT (opcional) |
| **Resultado** | Fila estándar `Exito|Codigo|Mensaje|EntidadId` — éxito: `HORARIO_BLOQUEADO` |
| **Reglas / transacción** | transacción explícita + `XACT_ABORT`; aislamiento SERIALIZABLE |
| **Errores de negocio** | `FECHA_PASADA`, `HORARIO_INVALIDO`, `MOTIVO_REQUERIDO`, `NO_ENCONTRADO`, `RESERVAS_EN_INTERVALO`, `SIN_PERMISO`, `SLOT_NO_EXISTE`, `TIPO_BLOQUEO_INVALIDO` |
| **Repository consumidor** | `SqlServerScheduleRepository::block()` |

### AdminHorarioDesbloquear

| | |
|---|---|
| **Nombre** | `api.usp_AdminHorarioDesbloquear` |
| **Propósito** | Levanta un bloqueo. |
| **Permiso** | `agenda.bloquear` |
| **Parámetros** | `@ActorUsuarioId` INT<br>`@BloqueoId` BIGINT |
| **Resultado** | Fila estándar `Exito|Codigo|Mensaje|EntidadId` — éxito: `HORARIO_DESBLOQUEADO` |
| **Reglas / transacción** | transacción explícita + `XACT_ABORT`; aislamiento SERIALIZABLE; locks `UPDLOCK, HOLDLOCK` en orden fijo (slot → paciente) |
| **Errores de negocio** | `BLOQUEO_INACTIVO`, `NO_ENCONTRADO`, `SIN_PERMISO` |
| **Repository consumidor** | `SqlServerScheduleRepository::unblock()` |

### AdminBloqueosListar

| | |
|---|---|
| **Nombre** | `api.usp_AdminBloqueosListar` |
| **Propósito** | Listado paginado de bloqueos. |
| **Permiso** | `agenda.ver`, `agenda.gestionar`, `reservas.gestionar` (según el caso) |
| **Parámetros** | `@ActorUsuarioId` INT<br>`@MedicoId` INT (opcional)<br>`@FechaDesde` DATE (opcional)<br>`@FechaHasta` DATE (opcional)<br>`@SoloActivos` BIT (opcional)<br>`@Pagina` INT (opcional)<br>`@TamanoPagina` INT (opcional) |
| **Resultado** | Result set paginado (`TotalFilas`) |
| **Reglas / transacción** | solo lectura |
| **Errores de negocio** | `SIN_PERMISO (error 50403 → 403)` |
| **Repository consumidor** | `SqlServerScheduleRepository::listBlocks()` |

### AgendaFechasDisponibles

| | |
|---|---|
| **Nombre** | `api.usp_AgendaFechasDisponibles` |
| **Propósito** | Fechas con horarios libres para una especialidad (wizard de reserva). |
| **Permiso** | `reservas.crear`, `agenda.ver` (según el caso) |
| **Parámetros** | `@ActorUsuarioId` INT<br>`@EspecialidadId` INT<br>`@MedicoId` INT (opcional)<br>`@SedeId` INT (opcional)<br>`@FechaDesde` DATE (opcional)<br>`@FechaHasta` DATE (opcional) |
| **Resultado** | Result set |
| **Reglas / transacción** | solo lectura |
| **Errores de negocio** | — |
| **Repository consumidor** | `SqlServerScheduleRepository::availableDates()` |

### AgendaHorariosDisponibles

| | |
|---|---|
| **Nombre** | `api.usp_AgendaHorariosDisponibles` |
| **Propósito** | Horarios libres de una fecha. |
| **Permiso** | `reservas.crear`, `agenda.ver` (según el caso) |
| **Parámetros** | `@ActorUsuarioId` INT<br>`@EspecialidadId` INT<br>`@Fecha` DATE<br>`@MedicoId` INT (opcional)<br>`@SedeId` INT (opcional) |
| **Resultado** | Result set |
| **Reglas / transacción** | solo lectura |
| **Errores de negocio** | — |
| **Repository consumidor** | `SqlServerScheduleRepository::availableSlots()` |

### AgendaHorariosEstado

| | |
|---|---|
| **Nombre** | `api.usp_AgendaHorariosEstado` |
| **Propósito** | Horarios de una fecha con su estado visual (disponible/ocupado/bloqueado). |
| **Permiso** | `reservas.crear`, `agenda.ver` (según el caso) |
| **Parámetros** | `@ActorUsuarioId` INT<br>`@EspecialidadId` INT<br>`@Fecha` DATE<br>`@MedicoId` INT (opcional)<br>`@SedeId` INT (opcional) |
| **Resultado** | Result set |
| **Reglas / transacción** | solo lectura |
| **Errores de negocio** | — |
| **Repository consumidor** | `SqlServerScheduleRepository::slotStates()` |

### AgendaObtenerDia

| | |
|---|---|
| **Nombre** | `api.usp_AgendaObtenerDia` |
| **Propósito** | Agenda del día de un médico (vista de recepción/admin). |
| **Permiso** | `agenda.gestionar`, `reservas.gestionar`, `agenda.ver` (según el caso) |
| **Parámetros** | `@ActorUsuarioId` INT<br>`@MedicoId` INT (opcional)<br>`@Fecha` DATE |
| **Resultado** | Result set |
| **Reglas / transacción** | solo lectura |
| **Errores de negocio** | — |
| **Repository consumidor** | `SqlServerScheduleRepository::doctorDay()` |

### MedicoAgendaPropia

| | |
|---|---|
| **Nombre** | `api.usp_MedicoAgendaPropia` |
| **Propósito** | Agendas del médico autenticado en un rango. |
| **Permiso** | `agenda.ver` |
| **Parámetros** | `@ActorUsuarioId` INT<br>`@FechaDesde` DATE (opcional)<br>`@FechaHasta` DATE (opcional) |
| **Resultado** | Result set |
| **Reglas / transacción** | solo lectura |
| **Errores de negocio** | — |
| **Repository consumidor** | `SqlServerScheduleRepository::ownAgenda()` |

## Reservas

### ReservaCrear

| | |
|---|---|
| **Nombre** | `api.usp_ReservaCrear` |
| **Propósito** | Reserva un horario (paciente para sí o personal para un paciente). Idempotente y a prueba de carreras. |
| **Permiso** | `reservas.crear`, `reservas.gestionar`, `reservas.propias` (según el caso) |
| **Parámetros** | `@ActorUsuarioId` INT<br>`@PacienteId` INT (opcional)<br>`@HorarioMedicoId` BIGINT<br>`@Observacion` NVARCHAR(250) (opcional)<br>`@IdempotencyKey` UNIQUEIDENTIFIER |
| **Resultado** | Fila estándar `Exito|Codigo|Mensaje|EntidadId` — éxito: `RESERVA_CREADA`; reintento con la misma clave → `RESERVA_EXISTENTE` (Exito=1, misma `EntidadId`) |
| **Reglas / transacción** | transacción explícita + `XACT_ABORT`; aislamiento SERIALIZABLE; locks `UPDLOCK, HOLDLOCK` en orden fijo (slot → paciente); idempotencia por `IdempotencyKey` (índice único) |
| **Errores de negocio** | `AGENDA_INACTIVA`, `ANTICIPACION_INSUFICIENTE`, `CONSULTORIO_CRUCE`, `FECHA_PASADA`, `FUERA_DE_RANGO`, `IDEMPOTENCIA_CONFLICTO`, `IDEMPOTENCIA_REQUERIDA`, `LIMITE_RESERVAS`, `MEDICO_CRUCE`, `MEDICO_INACTIVO`, `PACIENTE_CITA_SOLAPADA`, `PACIENTE_INACTIVO`, `PACIENTE_REQUERIDO`, `SIN_PERMISO`, `SLOT_BLOQUEADO`, `SLOT_NO_EXISTE`, `SLOT_OCUPADO` |
| **Repository consumidor** | `SqlServerReservationRepository::create()` |

### RecepcionReservaCrear

| | |
|---|---|
| **Nombre** | `api.usp_RecepcionReservaCrear` |
| **Propósito** | Reserva hecha por recepción para un paciente elegido. |
| **Permiso** | `reservas.gestionar`, `reservas.crear` (según el caso) |
| **Parámetros** | `@ActorUsuarioId` INT<br>`@PacienteId` INT<br>`@HorarioMedicoId` BIGINT<br>`@Observacion` NVARCHAR(250) (opcional)<br>`@IdempotencyKey` UNIQUEIDENTIFIER |
| **Resultado** | Fila estándar `Exito|Codigo|Mensaje|EntidadId`; reintento con la misma clave → `RESERVA_EXISTENTE` (Exito=1, misma `EntidadId`) |
| **Reglas / transacción** | idempotencia por `IdempotencyKey` (índice único) |
| **Errores de negocio** | `SIN_PERMISO` |
| **Repository consumidor** | `SqlServerReservationRepository::createForPatient()` |

### ReservaCancelar

| | |
|---|---|
| **Nombre** | `api.usp_ReservaCancelar` |
| **Propósito** | Cancela una reserva con motivo (plazo mínimo, control optimista). |
| **Permiso** | `reservas.cancelar`, `reservas.gestionar`, `reservas.propias` (según el caso) |
| **Parámetros** | `@ActorUsuarioId` INT<br>`@ReservaId` BIGINT<br>`@MotivoCancelacionId` TINYINT<br>`@Observacion` NVARCHAR(250) (opcional)<br>`@VersionFila` VARCHAR(18) |
| **Resultado** | Fila estándar `Exito|Codigo|Mensaje|EntidadId` — éxito: `RESERVA_CANCELADA` |
| **Reglas / transacción** | transacción explícita + `XACT_ABORT`; aislamiento SERIALIZABLE; locks `UPDLOCK, HOLDLOCK` en orden fijo (slot → paciente); control optimista por `rowversion` |
| **Errores de negocio** | `CITA_PASADA`, `CONFLICTO_EDICION`, `ESTADO_INVALIDO`, `FUERA_DE_PLAZO`, `MOTIVO_INVALIDO`, `NO_ENCONTRADO`, `OBSERVACION_REQUERIDA`, `SIN_PERMISO` |
| **Repository consumidor** | `SqlServerReservationRepository::cancel()` |

### ReservaReprogramar

| | |
|---|---|
| **Nombre** | `api.usp_ReservaReprogramar` |
| **Propósito** | Mueve una reserva a otro horario: cierra la original y crea la nueva en una sola transacción. |
| **Permiso** | `reservas.reprogramar`, `reservas.gestionar`, `reservas.propias` (según el caso) |
| **Parámetros** | `@ActorUsuarioId` INT<br>`@ReservaId` BIGINT<br>`@NuevoHorarioMedicoId` BIGINT<br>`@Motivo` NVARCHAR(250) (opcional)<br>`@VersionFila` VARCHAR(18)<br>`@IdempotencyKey` UNIQUEIDENTIFIER |
| **Resultado** | Fila estándar `Exito|Codigo|Mensaje|EntidadId` — éxito: `RESERVA_REPROGRAMADA`; reintento con la misma clave → `RESERVA_EXISTENTE` (Exito=1, misma `EntidadId`) |
| **Reglas / transacción** | transacción explícita + `XACT_ABORT`; aislamiento SERIALIZABLE; locks `UPDLOCK, HOLDLOCK` en orden fijo (slot → paciente); control optimista por `rowversion`; idempotencia por `IdempotencyKey` (índice único) |
| **Errores de negocio** | `AGENDA_INACTIVA`, `ANTICIPACION_INSUFICIENTE`, `CITA_PASADA`, `CONFLICTO_EDICION`, `CONSULTORIO_CRUCE`, `ESPECIALIDAD_DISTINTA`, `ESTADO_INVALIDO`, `FECHA_PASADA`, `FUERA_DE_PLAZO`, `FUERA_DE_RANGO`, `IDEMPOTENCIA_CONFLICTO`, `IDEMPOTENCIA_REQUERIDA`, `LIMITE_RESERVAS`, `MEDICO_CRUCE`, `MEDICO_INACTIVO`, `MISMO_HORARIO`, `NO_ENCONTRADO`, `PACIENTE_CITA_SOLAPADA`, `PACIENTE_INACTIVO`, `SIN_PERMISO`, `SLOT_BLOQUEADO`, `SLOT_NO_EXISTE`, `SLOT_OCUPADO` |
| **Repository consumidor** | `SqlServerReservationRepository::reschedule()` |

### MedicoMarcarAtendida

| | |
|---|---|
| **Nombre** | `api.usp_MedicoMarcarAtendida` |
| **Propósito** | Cierra la cita como ATENDIDA (solo después de su inicio). |
| **Permiso** | autenticado |
| **Parámetros** | `@ActorUsuarioId` INT<br>`@ReservaId` BIGINT<br>`@Nota` NVARCHAR(250) (opcional)<br>`@VersionFila` VARCHAR(18) |
| **Resultado** | Result set |
| **Reglas / transacción** | transacción explícita + `XACT_ABORT`; control optimista por `rowversion` |
| **Errores de negocio** | `CITA_NO_INICIADA`, `CONFLICTO_EDICION`, `ESTADO_INVALIDO`, `NO_ENCONTRADO`, `SIN_PERMISO` |
| **Repository consumidor** | `SqlServerReservationRepository::markAttended()` |

### MedicoMarcarNoAsistio

| | |
|---|---|
| **Nombre** | `api.usp_MedicoMarcarNoAsistio` |
| **Propósito** | Cierra la cita como NO_ASISTIO (solo después de su inicio). |
| **Permiso** | autenticado |
| **Parámetros** | `@ActorUsuarioId` INT<br>`@ReservaId` BIGINT<br>`@Nota` NVARCHAR(250) (opcional)<br>`@VersionFila` VARCHAR(18) |
| **Resultado** | Result set |
| **Reglas / transacción** | transacción explícita + `XACT_ABORT`; control optimista por `rowversion` |
| **Errores de negocio** | `CITA_NO_INICIADA`, `CONFLICTO_EDICION`, `ESTADO_INVALIDO`, `NO_ENCONTRADO`, `SIN_PERMISO` |
| **Repository consumidor** | `SqlServerReservationRepository::markNoShow()` |

### ReservaObtenerDetalle

| | |
|---|---|
| **Nombre** | `api.usp_ReservaObtenerDetalle` |
| **Propósito** | Detalle de una reserva si el actor tiene acceso (dueño, médico o personal). |
| **Permiso** | `reservas.propias`, `reservas.gestionar`, `agenda.ver`, `reservas.cancelar`, `reservas.reprogramar`, `reservas.estado` (según el caso) |
| **Parámetros** | `@ActorUsuarioId` INT<br>`@ReservaId` BIGINT |
| **Resultado** | Result set |
| **Reglas / transacción** | solo lectura |
| **Errores de negocio** | — |
| **Repository consumidor** | `SqlServerReservationRepository::find()` |

### ReservaHistorial

| | |
|---|---|
| **Nombre** | `api.usp_ReservaHistorial` |
| **Propósito** | Historial de estados de una reserva. |
| **Permiso** | autenticado |
| **Parámetros** | `@ActorUsuarioId` INT<br>`@ReservaId` BIGINT |
| **Resultado** | Result set |
| **Reglas / transacción** | solo lectura |
| **Errores de negocio** | — |
| **Repository consumidor** | `SqlServerReservationRepository::history()` |

### ReservaMisReservas

| | |
|---|---|
| **Nombre** | `api.usp_ReservaMisReservas` |
| **Propósito** | Citas del paciente autenticado (próximas / pasadas). |
| **Permiso** | `reservas.propias` |
| **Parámetros** | `@ActorUsuarioId` INT<br>`@Vista` VARCHAR(10) (opcional)<br>`@EstadoReservaId` TINYINT (opcional)<br>`@Pagina` INT (opcional)<br>`@TamanoPagina` INT (opcional) |
| **Resultado** | Result set paginado (`TotalFilas`) |
| **Reglas / transacción** | solo lectura |
| **Errores de negocio** | — |
| **Repository consumidor** | `SqlServerReservationRepository::mine()` |

### AdminReservasBuscar

| | |
|---|---|
| **Nombre** | `api.usp_AdminReservasBuscar` |
| **Propósito** | Búsqueda paginada de reservas para recepción/admin. |
| **Permiso** | `reservas.gestionar` |
| **Parámetros** | `@ActorUsuarioId` INT<br>`@Texto` NVARCHAR(100) (opcional)<br>`@FechaDesde` DATE (opcional)<br>`@FechaHasta` DATE (opcional)<br>`@EstadoReservaId` TINYINT (opcional)<br>`@MedicoId` INT (opcional)<br>`@EspecialidadId` INT (opcional)<br>`@SedeId` INT (opcional)<br>`@PacienteId` INT (opcional)<br>`@Pagina` INT (opcional)<br>`@TamanoPagina` INT (opcional) |
| **Resultado** | Result set paginado (`TotalFilas`) |
| **Reglas / transacción** | solo lectura |
| **Errores de negocio** | `SIN_PERMISO (error 50403 → 403)` |
| **Repository consumidor** | `SqlServerReservationRepository::search()` |

### MedicoReservasDia

| | |
|---|---|
| **Nombre** | `api.usp_MedicoReservasDia` |
| **Propósito** | Citas del día del médico. |
| **Permiso** | `reservas.gestionar`, `agenda.ver` (según el caso) |
| **Parámetros** | `@ActorUsuarioId` INT<br>`@Fecha` DATE (opcional)<br>`@MedicoId` INT (opcional) |
| **Resultado** | Result set |
| **Reglas / transacción** | solo lectura |
| **Errores de negocio** | — |
| **Repository consumidor** | `SqlServerReservationRepository::doctorDay()` |

## Auditoría

### AuditoriaBuscar

| | |
|---|---|
| **Nombre** | `api.usp_AuditoriaBuscar` |
| **Propósito** | Consulta paginada de la bitácora. |
| **Permiso** | `auditoria.ver` |
| **Parámetros** | `@ActorUsuarioId` INT<br>`@Texto` NVARCHAR(100) (opcional)<br>`@UsuarioId` INT (opcional)<br>`@Accion` VARCHAR(50) (opcional)<br>`@Exitoso` BIT (opcional)<br>`@FechaDesde` DATE (opcional)<br>`@FechaHasta` DATE (opcional)<br>`@Pagina` INT (opcional)<br>`@TamanoPagina` INT (opcional)<br>`@Entidad` VARCHAR(50) (opcional)<br>`@CorrelationIdFiltro` VARCHAR(64) (opcional) |
| **Resultado** | Result set paginado (`TotalFilas`) |
| **Reglas / transacción** | solo lectura |
| **Errores de negocio** | `SIN_PERMISO (error 50403 → 403)` |
| **Repository consumidor** | `SqlServerAuditRepository::search()` |

### AuditoriaAcciones

| | |
|---|---|
| **Nombre** | `api.usp_AuditoriaAcciones` |
| **Propósito** | Acciones y entidades distintas para los filtros de bitácora. |
| **Permiso** | `auditoria.ver` |
| **Parámetros** | `@ActorUsuarioId` INT |
| **Resultado** | Result set |
| **Reglas / transacción** | solo lectura |
| **Errores de negocio** | `SIN_PERMISO (error 50403 → 403)` |
| **Repository consumidor** | `SqlServerAuditRepository::actions()` |

## Panel y reportes

### DashboardResumen

| | |
|---|---|
| **Nombre** | `api.usp_DashboardResumen` |
| **Propósito** | Indicadores del panel según el rol del actor. |
| **Permiso** | `reportes.ver`, `reservas.gestionar` (según el caso) |
| **Parámetros** | `@ActorUsuarioId` INT |
| **Resultado** | Result set |
| **Reglas / transacción** | solo lectura |
| **Errores de negocio** | — |
| **Repository consumidor** | `SqlServerReportRepository::dashboardSummary()` |

### DashboardSerieDiaria

| | |
|---|---|
| **Nombre** | `api.usp_DashboardSerieDiaria` |
| **Propósito** | Serie diaria de reservas para el gráfico del panel. |
| **Permiso** | `reportes.ver`, `reservas.gestionar` (según el caso) |
| **Parámetros** | `@ActorUsuarioId` INT |
| **Resultado** | Result set |
| **Reglas / transacción** | solo lectura |
| **Errores de negocio** | — |
| **Repository consumidor** | `SqlServerReportRepository::dailySeries()` |

### ReporteCancelaciones

| | |
|---|---|
| **Nombre** | `api.usp_ReporteCancelaciones` |
| **Propósito** | Reporte: cancelaciones por motivo y anticipación. |
| **Permiso** | autenticado |
| **Parámetros** | `@ActorUsuarioId` INT<br>`@FechaDesde` DATE (opcional)<br>`@FechaHasta` DATE (opcional)<br>`@EspecialidadId` INT (opcional) |
| **Resultado** | Result set |
| **Reglas / transacción** | solo lectura |
| **Errores de negocio** | — |
| **Repository consumidor** | `SqlServerReportRepository::cancellations()` |

### ReporteNoAsistencia

| | |
|---|---|
| **Nombre** | `api.usp_ReporteNoAsistencia` |
| **Propósito** | Reporte: tasa de no asistencia por médico. |
| **Permiso** | autenticado |
| **Parámetros** | `@ActorUsuarioId` INT<br>`@FechaDesde` DATE (opcional)<br>`@FechaHasta` DATE (opcional)<br>`@SedeId` INT (opcional) |
| **Resultado** | Result set |
| **Reglas / transacción** | solo lectura |
| **Errores de negocio** | — |
| **Repository consumidor** | `SqlServerReportRepository::noShows()` |

## Vistas `api.vw_*`

Solo lectura, consultadas con `StoredProcedureExecutor::view()` (filtros de igualdad parametrizados, columnas validadas).

| Vista | Consumidor |
|---|---|
| `api.vw_UsuariosRoles` | uso interno de otros SP / reportes |
| `api.vw_PacientesResumen` | uso interno de otros SP / reportes |
| `api.vw_MedicosCatalogo` | uso interno de otros SP / reportes |
| `api.vw_AgendasDetalle` | uso interno de otros SP / reportes |
| `api.vw_HorariosDisponibilidad` | uso interno de otros SP / reportes |
| `api.vw_ReservasDetalle` | uso interno de otros SP / reportes |
| `api.vw_AuditoriaDetalle` | uso interno de otros SP / reportes |
| `api.vw_Especialidades` | `SqlServerCatalogRepository` |
| `api.vw_Sedes` | `SqlServerCatalogRepository` |
| `api.vw_Consultorios` | `SqlServerCatalogRepository` |
| `api.vw_TiposAtencion` | `SqlServerCatalogRepository` |
| `api.vw_EstadosReserva` | `SqlServerCatalogRepository` |
| `api.vw_MotivosCancelacion` | `SqlServerCatalogRepository` |
| `api.vw_Roles` | `SqlServerUserRepository` |
| `api.vw_Permisos` | `SqlServerUserRepository` |
| `api.vw_Configuracion` | `SqlServerCatalogRepository` |
