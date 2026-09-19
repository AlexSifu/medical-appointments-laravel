<?php

namespace App\Services;

use App\DTO\PagedResult;
use App\DTO\ProcedureResult;
use App\DTO\UserAccountData;
use App\Exceptions\NotFoundException;
use App\Models\User;
use App\Repositories\Contracts\AuthRepositoryInterface;
use App\Repositories\Contracts\CatalogRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Hashing\Hasher;

/** Administración de usuarios, roles y catálogos. */
final class AdminService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly CatalogRepositoryInterface $catalogs,
        private readonly AuthRepositoryInterface $auth,
        private readonly Hasher $hasher,
    ) {}

    /** @return PagedResult<UserAccountData> */
    public function searchUsers(User $actor, ?string $text, ?string $role, ?bool $active, int $page, int $perPage = 20): PagedResult
    {
        return $this->users->search($actor->id(), $text, $role, $active, $page, $perPage);
    }

    public function findUser(User $actor, int $userId): UserAccountData
    {
        return $this->users->find($actor->id(), $userId) ?? throw new NotFoundException('El usuario no existe.');
    }

    /** La contraseña se convierte a bcrypt en PHP; SQL Server solo recibe el hash. */
    public function createUser(User $actor, array $data): ProcedureResult
    {
        $hash = $this->hasher->make((string) $data['password']);
        unset($data['password'], $data['password_confirmation']);

        return $this->users->create($actor->id(), $data, $hash, 'BCRYPT')->throwIfFailed();
    }

    public function updateUser(User $actor, int $userId, array $data): ProcedureResult
    {
        return $this->users->update($actor->id(), $userId, $data, (string) $data['version'])->throwIfFailed();
    }

    public function assignRole(User $actor, int $userId, string $role, bool $assign): ProcedureResult
    {
        return $this->users->assignRole($actor->id(), $userId, $role, $assign)->throwIfFailed();
    }

    public function linkProfile(User $actor, int $userId, ?int $doctorId, ?int $patientId): ProcedureResult
    {
        return $this->users->linkProfile($actor->id(), $userId, $doctorId, $patientId)->throwIfFailed();
    }

    public function resetPassword(User $actor, int $userId, string $password): ProcedureResult
    {
        return $this->auth->updatePasswordHash($actor->id(), $userId, $this->hasher->make($password), 'BCRYPT')->throwIfFailed();
    }

    /** @return list<string> permisos efectivos del usuario */
    public function effectivePermissions(int $userId): array
    {
        return array_values(array_map(
            static fn (array $g): string => (string) $g['Codigo'],
            array_filter($this->users->effectiveGrants($userId), static fn (array $g): bool => $g['Tipo'] === 'PERMISO'),
        ));
    }

    /** @return list<array<string, mixed>> */
    public function roles(): array
    {
        return $this->users->roles();
    }

    /** @return list<array<string, mixed>> */
    public function permissionCatalog(): array
    {
        return $this->users->permissions();
    }

    public function saveSpecialty(User $actor, ?int $id, array $data): ProcedureResult
    {
        return $this->catalogs->saveSpecialty($actor->id(), $id, $data)->throwIfFailed();
    }

    public function saveBranch(User $actor, ?int $id, array $data): ProcedureResult
    {
        return $this->catalogs->saveBranch($actor->id(), $id, $data)->throwIfFailed();
    }
}
