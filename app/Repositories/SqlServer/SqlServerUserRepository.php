<?php

namespace App\Repositories\SqlServer;

use App\DTO\PagedResult;
use App\DTO\ProcedureResult;
use App\DTO\UserAccountData;
use App\Repositories\Contracts\UserRepositoryInterface;

final class SqlServerUserRepository extends SqlServerRepository implements UserRepositoryInterface
{
    private const SEARCH = 'api.usp_AdminUsuariosBuscar';

    private const FIND = 'api.usp_AdminUsuarioObtener';

    private const CREATE = 'api.usp_AdminUsuarioCrear';

    private const UPDATE = 'api.usp_AdminUsuarioActualizar';

    private const ASSIGN_ROLE = 'api.usp_AdminUsuarioAsignarRol';

    private const LINK_PROFILE = 'api.usp_AdminUsuarioVincularPerfil';

    private const BOOTSTRAP = 'api.usp_SistemaInicializarSuperadmin';

    private const GRANTS = 'api.usp_AuthObtenerPermisosUsuario';

    private const VIEW_ROLES = 'api.vw_Roles';

    private const VIEW_PERMISSIONS = 'api.vw_Permisos';

    public function search(int $actorId, ?string $text, ?string $role, ?bool $active, int $page, int $perPage): PagedResult
    {
        return $this->paged(self::SEARCH, [
            'ActorUsuarioId' => $actorId,
            'Texto' => self::text($text),
            'RolCodigo' => self::text($role),
            'Activo' => $active,
        ], $page, $perPage, UserAccountData::fromRow(...));
    }

    public function find(int $actorId, int $userId): ?UserAccountData
    {
        $row = $this->sql->selectOne(self::FIND, ['ActorUsuarioId' => $actorId, 'UsuarioId' => $userId]);

        return $row === null ? null : UserAccountData::fromRow($row);
    }

    public function create(int $actorId, array $data, string $passwordHash, string $algorithm): ProcedureResult
    {
        return $this->sql->command(self::CREATE, [
            'ActorUsuarioId' => $actorId,
            'NombreUsuario' => $data['username'],
            'Email' => $data['email'] ?? null,
            'Nombres' => $data['first_names'],
            'Apellidos' => $data['last_names'],
            'PasswordHash' => $passwordHash,
            'PasswordAlgoritmo' => $algorithm,
            'RolCodigo' => $data['role'],
            'MedicoId' => $data['doctor_id'] ?? null,
            'PacienteId' => $data['patient_id'] ?? null,
        ]);
    }

    public function update(int $actorId, int $userId, array $data, string $version): ProcedureResult
    {
        return $this->sql->command(self::UPDATE, [
            'ActorUsuarioId' => $actorId,
            'UsuarioId' => $userId,
            'Email' => $data['email'] ?? null,
            'Nombres' => $data['first_names'],
            'Apellidos' => $data['last_names'],
            'Activo' => (bool) $data['active'],
            'VersionFila' => $version,
        ]);
    }

    public function bootstrapSuperadmin(array $data, string $passwordHash, string $algorithm): ProcedureResult
    {
        return $this->sql->command(self::BOOTSTRAP, [
            'NombreUsuario' => $data['username'],
            'Email' => $data['email'] ?? null,
            'Nombres' => $data['first_names'],
            'Apellidos' => $data['last_names'],
            'PasswordHash' => $passwordHash,
            'PasswordAlgoritmo' => $algorithm,
        ], ['CorrelationId']);
    }

    public function assignRole(int $actorId, int $userId, string $role, bool $assign): ProcedureResult
    {
        return $this->sql->command(self::ASSIGN_ROLE, [
            'ActorUsuarioId' => $actorId,
            'UsuarioId' => $userId,
            'RolCodigo' => $role,
            'Asignar' => $assign,
        ]);
    }

    public function linkProfile(int $actorId, int $userId, ?int $doctorId, ?int $patientId): ProcedureResult
    {
        return $this->sql->command(self::LINK_PROFILE, [
            'ActorUsuarioId' => $actorId,
            'UsuarioId' => $userId,
            'MedicoId' => $doctorId,
            'PacienteId' => $patientId,
        ]);
    }

    public function roles(): array
    {
        return $this->sql->view(self::VIEW_ROLES, ['Activo' => true], 'Nivel DESC');
    }

    public function permissions(): array
    {
        return $this->sql->view(self::VIEW_PERMISSIONS, [], 'Modulo, Codigo');
    }

    public function effectiveGrants(int $userId): array
    {
        return $this->sql->select(self::GRANTS, ['UsuarioId' => $userId]);
    }
}
