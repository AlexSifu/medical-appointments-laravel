# Roles y permisos

Fuente de verdad: `seg.Roles`, `seg.Permisos`, `seg.RolPermiso` (seed del MASTER). Laravel no tiene una matriz propia: lee los
permisos efectivos del usuario con `api.usp_AuthObtenerPermisosUsuario` (se refrescan cada 5 min) y los usa para el menú y
el middleware `permission:`. **Cada SP vuelve a verificar el permiso** y, cuando aplica, la pertenencia del recurso.

## Roles

| Id | Código | Nivel | Descripción |
|---|---|---|---|
| 1 | SUPERADMIN | 100 | Control total de la plataforma |
| 2 | ADMINISTRADOR | 80 | Gestión de médicos, agenda, usuarios y reservas |
| 3 | RECEPCIONISTA | 50 | Registro de pacientes y gestión de reservas |
| 4 | MEDICO | 40 | Consulta de agenda propia y cierre de citas |
| 5 | PACIENTE | 10 | Reserva y gestión de citas propias |
| 6 | AUDITOR | 30 | Consulta de bitácora y reportes (solo lectura) |

Un usuario puede tener varios roles; el panel se elige por precedencia (`Role::PRECEDENCE`).

## Matriz rol × permiso (25 permisos)

| Permiso | Descripción | SUPERADMIN | ADMIN | RECEPCIÓN | MÉDICO | PACIENTE | AUDITOR |
|---|---|:-:|:-:|:-:|:-:|:-:|:-:|
| `usuarios.ver` | Ver usuarios del sistema | ✔ | ✔ | — | — | — | — |
| `usuarios.crear` | Crear usuarios | ✔ | ✔ | — | — | — | — |
| `usuarios.editar` | Editar y activar/desactivar usuarios | ✔ | ✔ | — | — | — | — |
| `usuarios.roles` | Asignar y revocar roles | ✔ | ✔ | — | — | — | — |
| `pacientes.ver` | Ver y buscar pacientes | ✔ | ✔ | ✔ | — | — | — |
| `pacientes.crear` | Registrar pacientes | ✔ | ✔ | ✔ | — | — | — |
| `pacientes.editar` | Editar datos de pacientes | ✔ | ✔ | ✔ | — | — | — |
| `medicos.ver` | Ver directorio de médicos | ✔ | ✔ | ✔ | ✔ | ✔ | — |
| `medicos.crear` | Registrar médicos | ✔ | ✔ | — | — | — | — |
| `medicos.editar` | Editar médicos, especialidades y sedes | ✔ | ✔ | — | — | — | — |
| `especialidades.gestionar` | Gestionar especialidades | ✔ | ✔ | — | — | — | — |
| `sedes.gestionar` | Gestionar sedes | ✔ | ✔ | — | — | — | — |
| `consultorios.gestionar` | Gestionar consultorios | ✔ | ✔ | — | — | — | — |
| `agenda.ver` | Ver agendas | ✔ | ✔ | ✔ | ✔ | — | — |
| `agenda.gestionar` | Crear, generar y desactivar agendas | ✔ | ✔ | — | — | — | — |
| `agenda.bloquear` | Bloquear y desbloquear horarios | ✔ | ✔ | — | — | — | — |
| `reservas.propias` | Gestionar reservas propias (paciente) | ✔ | — | — | — | ✔ | — |
| `reservas.crear` | Crear reservas | ✔ | ✔ | ✔ | — | ✔ | — |
| `reservas.reprogramar` | Reprogramar reservas | ✔ | ✔ | ✔ | — | ✔ | — |
| `reservas.cancelar` | Cancelar reservas | ✔ | ✔ | ✔ | — | ✔ | — |
| `reservas.gestionar` | Gestionar reservas de cualquier paciente | ✔ | ✔ | ✔ | — | — | — |
| `reservas.estado` | Marcar reservas como atendidas o no asistió | ✔ | ✔ | — | ✔ | — | — |
| `auditoria.ver` | Consultar bitácora | ✔ | ✔ | — | — | — | ✔ |
| `reportes.ver` | Consultar reportes y dashboard | ✔ | ✔ | — | — | — | ✔ |
| `configuracion.gestionar` | Modificar configuración del sistema | ✔ | — | — | — | — | — |

## Reglas de pertenencia (en SQL, además del permiso)

| Caso | Regla |
|---|---|
| Paciente (`reservas.propias` sin `reservas.gestionar`) | Solo crea/ve/cancela/reprograma reservas de **su** ficha; un `@PacienteId` ajeno → `SIN_PERMISO` |
| Paciente cancela/reprograma | Debe hacerlo con al menos `HORAS_MINIMAS_CANCELACION` horas; motivos "solo personal" no disponibles |
| Médico (`reservas.estado`) | Solo cierra citas donde es el médico tratante y a partir de la hora de inicio |
| Médico consulta agenda | `MedicoAgendaPropia` / `MedicoReservasDia` devuelven solo su agenda |
| Detalle de reserva | `clin.fn_PuedeVerReserva`: dueño, médico tratante o personal con `reservas.gestionar` |
| Roles de administración | Solo SUPERADMIN asigna SUPERADMIN/ADMINISTRADOR; nadie se quita a sí mismo esos roles |
| Configuración | Solo SUPERADMIN (`configuracion.gestionar`) |

## Rutas y permisos

| Área | Rutas | Permiso requerido (cualquiera de) |
|---|---|---|
| Panel | `/dashboard` | autenticado |
| Reservar | `/reservar…`, `POST /reservas` | `reservas.crear` |
| Directorio | `/medicos` | `medicos.ver` |
| Detalle de cita | `/reservas/{id}` | `reservas.propias`, `reservas.gestionar`, `reservas.estado` |
| Cancelar / Reprogramar | `/reservas/{id}/cancelar`, `/reprogramar` | `reservas.cancelar` / `reservas.reprogramar` |
| Atendida / No asistió | `/reservas/{id}/atendida`, `/no-asistio` | `reservas.estado` |
| Paciente | `/mis-citas`, `/mi-perfil` | `reservas.propias` |
| Médico | `/medico/hoy`, `/medico/agenda` | `agenda.ver` |
| Recepción | `/recepcion` · `/recepcion/pacientes…` | `reservas.gestionar` · `pacientes.ver/crear/editar` |
| Admin reservas | `/admin/reservas` | `reservas.gestionar` |
| Admin médicos | `/admin/medicos…` | `medicos.ver/crear/editar` |
| Admin agendas | `/admin/agendas…` · `/admin/agendas/dia` · `/admin/bloqueos…` | `agenda.gestionar` · `agenda.gestionar, reservas.gestionar` · `agenda.bloquear` |
| Admin usuarios | `/admin/usuarios…` | `usuarios.ver/crear/editar/roles` |
| Catálogos | `/admin/especialidades…` · `/admin/sedes…` | `especialidades.gestionar` · `sedes.gestionar` |
| Auditoría | `/auditoria` | `auditoria.ver` |
| Reportes | `/reportes/{reporte?}` | `reportes.ver` |

Sin permiso: 403 con página propia (`errors/403`), o JSON `{"code":"SIN_PERMISO"}` para peticiones AJAX.
Verificado en `tests/Feature/AccessControlTest.php` y `BookingFlowTest.php` (paciente y auditor reciben 403).
