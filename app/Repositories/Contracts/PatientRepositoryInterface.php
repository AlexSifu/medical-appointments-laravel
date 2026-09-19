<?php

namespace App\Repositories\Contracts;

use App\DTO\PagedResult;
use App\DTO\PatientData;
use App\DTO\ProcedureResult;

interface PatientRepositoryInterface
{
    /** @return PagedResult<PatientData> */
    public function search(int $actorId, ?string $text, ?bool $active, int $page, int $perPage): PagedResult;

    public function find(int $actorId, int $patientId): ?PatientData;

    /** @param array<string, mixed> $data */
    public function create(int $actorId, array $data): ProcedureResult;

    /** @param array<string, mixed> $data */
    public function update(int $actorId, int $patientId, array $data, string $version): ProcedureResult;

    /** @param array<string, mixed> $data */
    public function updateOwnProfile(int $actorId, array $data, string $version): ProcedureResult;
}
