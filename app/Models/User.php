<?php

namespace App\Models;

use App\DTO\AuthenticatedUserData;
use Illuminate\Contracts\Auth\Authenticatable;
use LogicException;

/**
 * Usuario autenticado (seg.Usuarios) para el guard de sesión de Laravel.
 *
 * No es un modelo Eloquent: se construye desde AuthenticatedUserData (api.usp_AuthObtenerUsuario +
 * api.usp_AuthObtenerPermisosUsuario) y nunca contiene el hash de contraseña.
 * Crear/editar usuarios: api.usp_AdminUsuarioCrear / Actualizar / AsignarRol.
 */
final class User implements Authenticatable
{
    public const TABLE = 'seg.Usuarios';

    public function __construct(public readonly AuthenticatedUserData $data) {}

    public function getAuthIdentifierName(): string
    {
        return 'UsuarioId';
    }

    public function getAuthIdentifier(): int
    {
        return $this->data->userId;
    }

    public function getAuthPasswordName(): string
    {
        return 'PasswordHash';
    }

    /** El hash nunca se mantiene en memoria de sesión. */
    public function getAuthPassword(): string
    {
        throw new LogicException('El hash de contraseña no está disponible en la sesión.');
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void {}

    public function getRememberTokenName(): string
    {
        return '';
    }

    public function id(): int
    {
        return $this->data->userId;
    }

    public function name(): string
    {
        return $this->data->fullName();
    }

    public function can(string $permission): bool
    {
        return $this->data->hasPermission($permission);
    }

    public function canAny(string ...$permissions): bool
    {
        return $this->data->hasAnyPermission(...$permissions);
    }

    public function hasRole(string $role): bool
    {
        return $this->data->hasRole($role);
    }

    public function isPatient(): bool
    {
        return $this->data->patientId !== null && $this->can(Permission::RESERVATIONS_OWN) && ! $this->can(Permission::RESERVATIONS_MANAGE);
    }

    public function isDoctor(): bool
    {
        return $this->data->doctorId !== null && $this->hasRole(Role::DOCTOR);
    }
}
