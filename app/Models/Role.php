<?php

namespace App\Models;

/**
 * Rol RBAC (seg.Roles). Representación de dominio: la asignación se hace con api.usp_AdminUsuarioAsignarRol.
 */
final class Role
{
    public const TABLE = 'seg.Roles';

    public const SUPERADMIN = 'SUPERADMIN';

    public const ADMIN = 'ADMINISTRADOR';

    public const RECEPTIONIST = 'RECEPCIONISTA';

    public const DOCTOR = 'MEDICO';

    public const PATIENT = 'PACIENTE';

    public const AUDITOR = 'AUDITOR';

    /** Orden de precedencia para elegir el panel de inicio de un usuario con varios roles. */
    public const PRECEDENCE = [self::SUPERADMIN, self::ADMIN, self::RECEPTIONIST, self::DOCTOR, self::AUDITOR, self::PATIENT];

    public const LABELS = [
        self::SUPERADMIN => 'Superadministrador',
        self::ADMIN => 'Administrador',
        self::RECEPTIONIST => 'Recepcionista',
        self::DOCTOR => 'Médico',
        self::PATIENT => 'Paciente',
        self::AUDITOR => 'Auditor',
    ];

    public static function label(string $code): string
    {
        return self::LABELS[$code] ?? $code;
    }
}
