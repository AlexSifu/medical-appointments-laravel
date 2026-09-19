<?php

namespace App\DTO;

use App\Models\Role;
use App\Support\Row;

/**
 * Usuario autenticado: identidad, roles y permisos efectivos leídos de SQL Server.
 * Se guarda en sesión como array (sin hash de contraseña).
 */
final readonly class AuthenticatedUserData
{
    /**
     * @param  list<string>  $roles
     * @param  list<string>  $permissions
     */
    public function __construct(
        public int $userId,
        public string $username,
        public ?string $email,
        public string $firstNames,
        public string $lastNames,
        public array $roles,
        public array $permissions,
        public ?int $doctorId = null,
        public ?int $patientId = null,
        public int $loadedAt = 0,
    ) {}

    /**
     * @param  array<string, mixed>  $user  fila de api.usp_AuthObtenerUsuario
     * @param  list<array<string, mixed>>  $grants  filas de api.usp_AuthObtenerPermisosUsuario (Tipo, Codigo)
     */
    public static function fromRows(array $user, array $grants): self
    {
        $roles = [];
        $permissions = [];
        foreach ($grants as $grant) {
            $code = Row::str($grant, 'Codigo');
            if (Row::str($grant, 'Tipo') === 'ROL') {
                $roles[] = $code;
            } else {
                $permissions[] = $code;
            }
        }

        return new self(
            userId: Row::int($user, 'UsuarioId'),
            username: Row::str($user, 'NombreUsuario'),
            email: Row::strOrNull($user, 'Email'),
            firstNames: Row::str($user, 'Nombres'),
            lastNames: Row::str($user, 'Apellidos'),
            roles: array_values(array_unique($roles)),
            permissions: array_values(array_unique($permissions)),
            doctorId: Row::intOrNull($user, 'MedicoId'),
            patientId: Row::intOrNull($user, 'PacienteId'),
            loadedAt: time(),
        );
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            userId: (int) $data['userId'],
            username: (string) $data['username'],
            email: $data['email'] ?? null,
            firstNames: (string) $data['firstNames'],
            lastNames: (string) $data['lastNames'],
            roles: array_values(array_map('strval', $data['roles'] ?? [])),
            permissions: array_values(array_map('strval', $data['permissions'] ?? [])),
            doctorId: isset($data['doctorId']) ? (int) $data['doctorId'] : null,
            patientId: isset($data['patientId']) ? (int) $data['patientId'] : null,
            loadedAt: (int) ($data['loadedAt'] ?? 0),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    public function fullName(): string
    {
        return trim($this->firstNames.' '.$this->lastNames);
    }

    public function initials(): string
    {
        $first = mb_substr($this->firstNames, 0, 1);
        $last = mb_substr($this->lastNames, 0, 1);

        return mb_strtoupper($first.$last) ?: mb_strtoupper(mb_substr($this->username, 0, 2));
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }

    public function hasAnyPermission(string ...$permissions): bool
    {
        return array_intersect($permissions, $this->permissions) !== [];
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles, true);
    }

    /** Rol con mayor precedencia de interfaz (define el panel de inicio). */
    public function primaryRole(): ?string
    {
        foreach (Role::PRECEDENCE as $role) {
            if ($this->hasRole($role)) {
                return $role;
            }
        }

        return null;
    }
}
