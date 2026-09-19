<?php

namespace App\Repositories\SqlServer;

use App\DTO\DoctorData;
use App\DTO\PagedResult;
use App\DTO\ProcedureResult;
use App\Repositories\Contracts\DoctorRepositoryInterface;

final class SqlServerDoctorRepository extends SqlServerRepository implements DoctorRepositoryInterface
{
    private const SEARCH = 'api.usp_MedicosBuscar';

    private const FIND = 'api.usp_MedicoObtener';

    private const CREATE = 'api.usp_AdminMedicoCrear';

    private const UPDATE = 'api.usp_AdminMedicoActualizar';

    private const CHANGE_STATUS = 'api.usp_AdminMedicoCambiarEstado';

    private const ASSIGN_SPECIALTY = 'api.usp_AdminMedicoAsignarEspecialidad';

    private const ASSIGN_BRANCH = 'api.usp_AdminMedicoAsignarSede';

    public function search(int $actorId, ?string $text, ?int $specialtyId, ?int $branchId, ?bool $active, int $page, int $perPage): PagedResult
    {
        return $this->paged(self::SEARCH, [
            'ActorUsuarioId' => $actorId,
            'Texto' => self::text($text),
            'EspecialidadId' => $specialtyId,
            'SedeId' => $branchId,
            'Activo' => $active,
        ], $page, $perPage, DoctorData::fromRow(...));
    }

    public function find(int $actorId, int $doctorId): ?DoctorData
    {
        $row = $this->sql->selectOne(self::FIND, ['ActorUsuarioId' => $actorId, 'MedicoId' => $doctorId]);

        return $row === null ? null : DoctorData::fromRow($row);
    }

    public function create(int $actorId, array $data): ProcedureResult
    {
        return $this->sql->command(self::CREATE, [
            'ActorUsuarioId' => $actorId,
            'CMP' => $data['cmp'],
            'Nombres' => $data['first_names'],
            'Apellidos' => $data['last_names'],
            'Telefono' => $data['phone'] ?? null,
            'Email' => $data['email'] ?? null,
            'EspecialidadId' => (int) $data['specialty_id'],
            'SedeId' => (int) $data['branch_id'],
        ]);
    }

    public function update(int $actorId, int $doctorId, array $data, string $version): ProcedureResult
    {
        return $this->sql->command(self::UPDATE, [
            'ActorUsuarioId' => $actorId,
            'MedicoId' => $doctorId,
            'CMP' => $data['cmp'],
            'Nombres' => $data['first_names'],
            'Apellidos' => $data['last_names'],
            'Telefono' => $data['phone'] ?? null,
            'Email' => $data['email'] ?? null,
            'VersionFila' => $version,
        ]);
    }

    public function changeStatus(int $actorId, int $doctorId, bool $active, string $version): ProcedureResult
    {
        return $this->sql->command(self::CHANGE_STATUS, [
            'ActorUsuarioId' => $actorId,
            'MedicoId' => $doctorId,
            'Activo' => $active,
            'VersionFila' => $version,
        ]);
    }

    public function assignSpecialty(int $actorId, int $doctorId, int $specialtyId, bool $assign, bool $main = false): ProcedureResult
    {
        return $this->sql->command(self::ASSIGN_SPECIALTY, [
            'ActorUsuarioId' => $actorId,
            'MedicoId' => $doctorId,
            'EspecialidadId' => $specialtyId,
            'Asignar' => $assign,
            'EsPrincipal' => $main,
        ]);
    }

    public function assignBranch(int $actorId, int $doctorId, int $branchId, bool $assign): ProcedureResult
    {
        return $this->sql->command(self::ASSIGN_BRANCH, [
            'ActorUsuarioId' => $actorId,
            'MedicoId' => $doctorId,
            'SedeId' => $branchId,
            'Asignar' => $assign,
        ]);
    }
}
