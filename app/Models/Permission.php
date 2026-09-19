<?php

namespace App\Models;

/**
 * Permisos RBAC (seg.Permisos). La interfaz los usa para autorización preventiva;
 * cada procedimiento almacenado vuelve a validarlos (autorización doble).
 */
final class Permission
{
    public const TABLE = 'seg.Permisos';

    public const USERS_VIEW = 'usuarios.ver';

    public const USERS_CREATE = 'usuarios.crear';

    public const USERS_EDIT = 'usuarios.editar';

    public const USERS_ROLES = 'usuarios.roles';

    public const PATIENTS_VIEW = 'pacientes.ver';

    public const PATIENTS_CREATE = 'pacientes.crear';

    public const PATIENTS_EDIT = 'pacientes.editar';

    public const DOCTORS_VIEW = 'medicos.ver';

    public const DOCTORS_CREATE = 'medicos.crear';

    public const DOCTORS_EDIT = 'medicos.editar';

    public const SPECIALTIES_MANAGE = 'especialidades.gestionar';

    public const BRANCHES_MANAGE = 'sedes.gestionar';

    public const ROOMS_MANAGE = 'consultorios.gestionar';

    public const AGENDA_VIEW = 'agenda.ver';

    public const AGENDA_MANAGE = 'agenda.gestionar';

    public const AGENDA_BLOCK = 'agenda.bloquear';

    public const RESERVATIONS_OWN = 'reservas.propias';

    public const RESERVATIONS_CREATE = 'reservas.crear';

    public const RESERVATIONS_RESCHEDULE = 'reservas.reprogramar';

    public const RESERVATIONS_CANCEL = 'reservas.cancelar';

    public const RESERVATIONS_MANAGE = 'reservas.gestionar';

    public const RESERVATIONS_STATUS = 'reservas.estado';

    public const AUDIT_VIEW = 'auditoria.ver';

    public const REPORTS_VIEW = 'reportes.ver';

    public const SETTINGS_MANAGE = 'configuracion.gestionar';
}
