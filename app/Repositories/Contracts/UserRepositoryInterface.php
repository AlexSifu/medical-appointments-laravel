<?php

namespace App\Repositories\Contracts;

use App\DTO\PagedResult;
use App\DTO\ProcedureResult;
use App\DTO\UserAccountData;

interface UserRepositoryInterface
{
    /** @return PagedResult<UserAccountData> */
    public function search(int $actorId, ?string $text, ?string $role, ?bool $active, int $page, int $perPage): PagedResult;

    public function find(int $actorId, int $userId): ?UserAccountData;

    /** @param array<string, mixed> $data */
    public function create(int $actorId, array $data, string $passwordHash, string $algorithm): ProcedureResult;

    /** @param array<string, mixed> $data */
    public function update(int $actorId, int $userId, array $data, string $version): ProcedureResult;

    /** Arranque: crea el primer SUPERADMIN solo si no existe ninguno activo (YA_INICIALIZADO devuelve su id). */
    public function bootstrapSuperadmin(array $data, string $passwordHash, string $algorithm): ProcedureResult;

    public function assignRole(int $actorId, int $userId, string $role, bool $assign): ProcedureResult;

    public function linkProfile(int $actorId, int $userId, ?int $doctorId, ?int $patientId): ProcedureResult;

    /** @return list<array<string, mixed>> api.vw_Roles */
    public function roles(): array;

    /** @return list<array<string, mixed>> api.vw_Permisos */
    public function permissions(): array;

    /** @return list<array{Tipo: string, Codigo: string}> */
    public function effectiveGrants(int $userId): array;
}
