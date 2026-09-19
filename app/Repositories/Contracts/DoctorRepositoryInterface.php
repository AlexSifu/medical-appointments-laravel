<?php

namespace App\Repositories\Contracts;

use App\DTO\DoctorData;
use App\DTO\PagedResult;
use App\DTO\ProcedureResult;

interface DoctorRepositoryInterface
{
    /** @return PagedResult<DoctorData> */
    public function search(int $actorId, ?string $text, ?int $specialtyId, ?int $branchId, ?bool $active, int $page, int $perPage): PagedResult;

    public function find(int $actorId, int $doctorId): ?DoctorData;

    /** @param array<string, mixed> $data */
    public function create(int $actorId, array $data): ProcedureResult;

    /** @param array<string, mixed> $data */
    public function update(int $actorId, int $doctorId, array $data, string $version): ProcedureResult;

    public function changeStatus(int $actorId, int $doctorId, bool $active, string $version): ProcedureResult;

    public function assignSpecialty(int $actorId, int $doctorId, int $specialtyId, bool $assign, bool $main = false): ProcedureResult;

    public function assignBranch(int $actorId, int $doctorId, int $branchId, bool $assign): ProcedureResult;
}
