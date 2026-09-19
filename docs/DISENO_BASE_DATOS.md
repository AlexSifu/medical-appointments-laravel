# Diseño de base de datos — `ReservasMedicasWeb`

Script único: `database/sql/ReservasMedicasWeb_MASTER.sql` (idempotente: `IF OBJECT_ID … IS NULL CREATE TABLE`,
`CREATE OR ALTER` para programables, seeds con `INSERT … WHERE NOT EXISTS`; nunca `DROP` de tablas ni `DROP DATABASE`).
Validación: `ReservasMedicasWeb_VALIDATE.sql` (187 comprobaciones).

## Esquemas

| Esquema | Rol | Acceso de la aplicación |
|---|---|---|
| `api` | Superficie pública: 60 procedimientos `usp_*` y 16 vistas `vw_*` | `GRANT EXECUTE, SELECT` a `nexa_app_role` |
| `seg` | Usuarios, roles, permisos, configuración, utilidades de resultado/locks | `DENY` total |
| `clin` | Dominio clínico-administrativo: sedes, médicos, agendas, horarios, pacientes, reservas | `DENY` total |
| `audit` | Bitácora | `DENY` total |

Los procedimientos `api` acceden a `seg`/`clin`/`audit` por *ownership chaining* (mismo dueño `dbo`), así la cuenta de la
aplicación solo puede hacer lo que un SP permite.

## Tablas (24)

### `seg`
| Tabla | Propósito | Claves / reglas |
|---|---|---|
| `Roles` | 6 roles con nivel de precedencia | seed fijo 1–6 |
| `Permisos` | 25 permisos `modulo.accion` | `CK_Permisos_Codigo` |
| `RolPermiso` | Matriz rol × permiso | PK compuesta |
| `Usuarios` | Cuentas: hash, algoritmo, intentos fallidos, bloqueo, último acceso | `CK_Usuarios_Algoritmo` (BCRYPT/ARGON2ID/PBKDF2_SHA256), email único filtrado, `rowversion` |
| `UsuarioRol` | Roles por usuario (activo/inactivo) | |
| `ConfiguracionSistema` | Parámetros de negocio con mínimo/máximo | ver abajo |

### `clin`
| Tabla | Propósito | Claves / reglas |
|---|---|---|
| `Especialidades`, `Sedes`, `Consultorios`, `TiposAtencion` | Catálogos | consultorio único por sede |
| `Pacientes` | Ficha con documento único (`DNI`/`CE`/`PASAPORTE`), vínculo opcional a usuario | `UX_Pacientes_Usuario` filtrado, `CK_*` de formato y fecha |
| `Medicos` | CMP único, vínculo opcional a usuario | `UX_Medicos_Usuario` filtrado |
| `MedicoEspecialidad`, `MedicoSede` | Asignaciones N:M | una sola especialidad principal activa (`UX_MedicoEspecialidad_Principal`) |
| `PlantillasAgenda` | Registro de generaciones por rango | rango ≤ 92 días, días `1-7` |
| `AgendasMedicas` | Bloque de atención de un día: médico + especialidad + sede + consultorio + tipo | duración 10–120 min y que quepa |
| `BloqueosMedico` | Ausencias, reuniones, mantenimiento… | levantamiento coherente con `Activo` |
| `HorariosMedicos` | Slots generados de cada agenda | `UQ(AgendaId, HoraInicio)`, `rowversion` |
| `EstadosReserva` | CONFIRMADA, CANCELADA, REPROGRAMADA, ATENDIDA, NO_ASISTIO (con `OcupaHorario`, `EsFinal`) | |
| `TransicionesEstadoReserva` | Solo desde CONFIRMADA → {2,3,4,5} | |
| `MotivosCancelacion` | 6 motivos; 2 son solo para personal | |
| `Reservas` | La cita, con instantánea del slot (médico, especialidad, sede, consultorio, fecha, horas) | ver invariantes |
| `ReservaHistorial` | Cada transición con actor y fecha UTC | |

### `audit`
| Tabla | Propósito |
|---|---|
| `BitacoraSistema` | Acción, entidad, éxito/rechazo, detalle sin datos sensibles, IP, User-Agent, CorrelationId, fecha UTC |

## Invariantes de `clin.Reservas`

| Invariante | Mecanismo |
|---|---|
| Un slot tiene como máximo una reserva que lo ocupe | `UX_Reservas_HorarioOcupado` único **filtrado** `WHERE OcupaHorario = 1` |
| `OcupaHorario` coherente con el estado (1,4,5 ocupan; 2,3 liberan) | `CK_Reservas_OcupaHorario` |
| Una cancelación tiene motivo, actor y fecha | `CK_Reservas_Cancelacion` |
| Una reprogramada apunta a su reemplazo | `CK_Reservas_Reprogramada` + `ReservaOrigenId`/`ReservaReemplazoId` |
| Atendida/No asistió tienen quién y cuándo cerró | `CK_Reservas_Cierre` |
| Reintentos no duplican | `UX_Reservas_IdempotencyKey` |
| Código legible estable | `CodigoReserva` calculado persistido `NX-00000001`, único |
| Edición concurrente detectada | `VersionFila ROWVERSION` |

Aunque un SP tuviera un bug, el motor rechazaría una doble ocupación o un estado incoherente.

## Índices de consulta

`IX_Agendas_MedicoFecha`, `IX_Agendas_ConsultorioFecha`, `IX_Agendas_FechaEspecialidad` (búsqueda de disponibilidad);
`IX_Reservas_PacienteFecha`, `IX_Reservas_MedicoFecha`, `IX_Reservas_FechaEstado` (mis citas, agenda del médico, reportes);
`IX_Bitacora_Fecha`, `IX_Bitacora_UsuarioFecha`, `IX_Bitacora_Correlation` (auditoría); `IX_Pacientes_Apellidos`.

## Configuración (`seg.ConfiguracionSistema`)

| Clave | Defecto | Rango | Uso |
|---|---|---|---|
| `HORAS_MINIMAS_CANCELACION` | 2 | 0–72 | Plazo para que el paciente cancele/reprograme |
| `MAX_DIAS_RESERVA_FUTURA` | 60 | 1–365 | Horizonte de reserva |
| `MINUTOS_MINIMOS_ANTICIPACION` | 30 | 0–1440 | Anticipación mínima para reservar |
| `MAX_RESERVAS_ACTIVAS_PACIENTE` | 5 | 1–50 | Citas futuras simultáneas por paciente |
| `MAX_INTENTOS_LOGIN` | 5 | 3–20 | Intentos antes del bloqueo temporal |
| `MINUTOS_BLOQUEO_LOGIN` | 15 | 1–1440 | Duración del bloqueo |

Se modifican con `api.usp_AdminConfiguracionActualizar` (solo SUPERADMIN, valida rango).

## Programables internos

- **Funciones (10):** `clin.fn_AhoraLocal`, `clin.fn_LocalAUtc`, `clin.fn_FechaHora`, `clin.fn_DisponibilidadMedico`,
  `clin.fn_HorariosReservables`, `clin.fn_PuedeVerReserva`, `seg.fn_PermisosUsuario`, `seg.fn_TienePermiso`, `seg.fn_TieneRol`,
  `seg.fn_ConfigEntero`.
- **Procedimientos internos (13):** `seg.usp_Resultado`, `seg.usp_Rechazo`, `seg.usp_ErrorCapturado`, `seg.usp_ExigirPermiso`,
  `seg.usp_ObtenerLock`, `audit.usp_Registrar`, `clin.usp_PacienteValidarDatos`, `clin.usp_AgendaCrearInterno`,
  `clin.usp_AgendaValidarMaestros`, `clin.usp_ReservaTomarLocks`, `clin.usp_ReservaValidarEInsertar`, `clin.usp_ReservaCerrar`,
  `clin.usp_ReporteRango`.
- **Vistas `api` (16):** `vw_UsuariosRoles`, `vw_PacientesResumen`, `vw_MedicosCatalogo`, `vw_AgendasDetalle`,
  `vw_HorariosDisponibilidad`, `vw_ReservasDetalle`, `vw_AuditoriaDetalle`, `vw_Especialidades`, `vw_Sedes`, `vw_Consultorios`,
  `vw_TiposAtencion`, `vw_EstadosReserva`, `vw_MotivosCancelacion`, `vw_Roles`, `vw_Permisos`, `vw_Configuracion`.

## Hora

Fechas de cita en hora civil de la clínica (`America/Lima`, `clin.fn_AhoraLocal()` con `AT TIME ZONE`); marcas de auditoría
y creación en UTC (`SYSUTCDATETIME()`).

## Diagrama (simplificado)

```
seg.Usuarios ─┬─< seg.UsuarioRol >── seg.Roles ──< seg.RolPermiso >── seg.Permisos
              ├── clin.Pacientes (0..1)
              └── clin.Medicos   (0..1) ─┬─< clin.MedicoEspecialidad >── clin.Especialidades
                                         ├─< clin.MedicoSede >── clin.Sedes ──< clin.Consultorios
                                         ├─< clin.BloqueosMedico
                                         └─< clin.AgendasMedicas ──< clin.HorariosMedicos ──< clin.Reservas >── clin.Pacientes
                                                                                                │
                                                        clin.EstadosReserva / MotivosCancelacion ┘──< clin.ReservaHistorial
audit.BitacoraSistema (UsuarioId opcional)
```
