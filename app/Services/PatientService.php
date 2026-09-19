<?php

namespace App\Services;

use App\DTO\PagedResult;
use App\DTO\PatientData;
use App\DTO\ProcedureResult;
use App\Exceptions\NotFoundException;
use App\Models\User;
use App\Repositories\Contracts\PatientRepositoryInterface;

final class PatientService
{
    public function __construct(private readonly PatientRepositoryInterface $patients) {}

    /** @return PagedResult<PatientData> */
    public function search(User $actor, ?string $text, ?bool $active, int $page, int $perPage = 15): PagedResult
    {
        return $this->patients->search($actor->id(), $text, $active, $page, $perPage);
    }

    public function find(User $actor, int $patientId): PatientData
    {
        return $this->patients->find($actor->id(), $patientId) ?? throw new NotFoundException('El paciente no existe.');
    }

    /** Perfil del paciente autenticado. */
    public function own(User $actor): ?PatientData
    {
        return $actor->data->patientId === null ? null : $this->patients->find($actor->id(), $actor->data->patientId);
    }

    public function create(User $actor, array $data): ProcedureResult
    {
        return $this->patients->create($actor->id(), $data)->throwIfFailed();
    }

    public function update(User $actor, int $patientId, array $data): ProcedureResult
    {
        return $this->patients->update($actor->id(), $patientId, $data, (string) $data['version'])->throwIfFailed();
    }

    public function updateOwnProfile(User $actor, array $data): ProcedureResult
    {
        return $this->patients->updateOwnProfile($actor->id(), $data, (string) $data['version'])->throwIfFailed();
    }
}
