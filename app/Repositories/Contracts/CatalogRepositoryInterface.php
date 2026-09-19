<?php

namespace App\Repositories\Contracts;

use App\DTO\ProcedureResult;

/** Catálogos de solo lectura (vistas api.vw_*) y su mantenimiento por SP. */
interface CatalogRepositoryInterface
{
    /** @return list<array<string, mixed>> */
    public function specialties(bool $onlyActive = true): array;

    /** @return list<array<string, mixed>> */
    public function branches(bool $onlyActive = true): array;

    /** @return list<array<string, mixed>> */
    public function rooms(?int $branchId = null, bool $onlyActive = true): array;

    /** @return list<array<string, mixed>> */
    public function careTypes(): array;

    /** @return list<array<string, mixed>> */
    public function cancellationReasons(bool $includeStaffOnly): array;

    /** @return list<array<string, mixed>> */
    public function reservationStatuses(): array;

    /** @return list<array<string, mixed>> */
    public function configuration(): array;

    /** @param array<string, mixed> $data */
    public function saveSpecialty(int $actorId, ?int $id, array $data): ProcedureResult;

    /** @param array<string, mixed> $data */
    public function saveBranch(int $actorId, ?int $id, array $data): ProcedureResult;

    /** @param array<string, mixed> $data */
    public function saveRoom(int $actorId, ?int $id, array $data): ProcedureResult;

    public function updateConfiguration(int $actorId, string $key, string $value): ProcedureResult;

    public function forgetCache(): void;
}
