<?php

namespace App\Services;

use App\DTO\DoctorData;
use App\DTO\PagedResult;
use App\DTO\ProcedureResult;
use App\Exceptions\NotFoundException;
use App\Models\User;
use App\Repositories\Contracts\DoctorRepositoryInterface;

final class DoctorService
{
    public function __construct(private readonly DoctorRepositoryInterface $doctors) {}

    /** @return PagedResult<DoctorData> */
    public function search(User $actor, ?string $text, ?int $specialtyId, ?int $branchId, ?bool $active, int $page, int $perPage = 12): PagedResult
    {
        return $this->doctors->search($actor->id(), $text, $specialtyId, $branchId, $active, $page, $perPage);
    }

    public function find(User $actor, int $doctorId): DoctorData
    {
        return $this->doctors->find($actor->id(), $doctorId) ?? throw new NotFoundException('El médico no existe.');
    }

    public function create(User $actor, array $data): ProcedureResult
    {
        return $this->doctors->create($actor->id(), $data)->throwIfFailed();
    }

    public function update(User $actor, int $doctorId, array $data): ProcedureResult
    {
        return $this->doctors->update($actor->id(), $doctorId, $data, (string) $data['version'])->throwIfFailed();
    }

    public function changeStatus(User $actor, int $doctorId, bool $active, string $version): ProcedureResult
    {
        return $this->doctors->changeStatus($actor->id(), $doctorId, $active, $version)->throwIfFailed();
    }

    public function assignSpecialty(User $actor, int $doctorId, int $specialtyId, bool $assign, bool $main): ProcedureResult
    {
        return $this->doctors->assignSpecialty($actor->id(), $doctorId, $specialtyId, $assign, $main)->throwIfFailed();
    }

    public function assignBranch(User $actor, int $doctorId, int $branchId, bool $assign): ProcedureResult
    {
        return $this->doctors->assignBranch($actor->id(), $doctorId, $branchId, $assign)->throwIfFailed();
    }
}
