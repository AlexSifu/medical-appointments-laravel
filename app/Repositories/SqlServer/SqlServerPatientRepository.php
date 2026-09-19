<?php

namespace App\Repositories\SqlServer;

use App\DTO\PagedResult;
use App\DTO\PatientData;
use App\DTO\ProcedureResult;
use App\Repositories\Contracts\PatientRepositoryInterface;

final class SqlServerPatientRepository extends SqlServerRepository implements PatientRepositoryInterface
{
    private const SEARCH = 'api.usp_PacienteBuscar';

    private const FIND = 'api.usp_PacienteObtener';

    private const CREATE = 'api.usp_RecepcionPacienteCrear';

    private const UPDATE = 'api.usp_RecepcionPacienteActualizar';

    private const UPDATE_OWN = 'api.usp_PacienteActualizarPerfil';

    public function search(int $actorId, ?string $text, ?bool $active, int $page, int $perPage): PagedResult
    {
        return $this->paged(self::SEARCH, [
            'ActorUsuarioId' => $actorId,
            'Texto' => self::text($text),
            'Activo' => $active,
        ], $page, $perPage, PatientData::fromRow(...));
    }

    public function find(int $actorId, int $patientId): ?PatientData
    {
        $row = $this->sql->selectOne(self::FIND, ['ActorUsuarioId' => $actorId, 'PacienteId' => $patientId]);

        return $row === null ? null : PatientData::fromRow($row);
    }

    public function create(int $actorId, array $data): ProcedureResult
    {
        return $this->sql->command(self::CREATE, ['ActorUsuarioId' => $actorId] + self::fields($data));
    }

    public function update(int $actorId, int $patientId, array $data, string $version): ProcedureResult
    {
        return $this->sql->command(self::UPDATE, ['ActorUsuarioId' => $actorId, 'PacienteId' => $patientId]
            + self::fields($data)
            + ['Activo' => (bool) ($data['active'] ?? true), 'VersionFila' => $version]);
    }

    public function updateOwnProfile(int $actorId, array $data, string $version): ProcedureResult
    {
        return $this->sql->command(self::UPDATE_OWN, [
            'ActorUsuarioId' => $actorId,
            'Telefono' => $data['phone'] ?? null,
            'Email' => $data['email'] ?? null,
            'Direccion' => $data['address'] ?? null,
            'ContactoEmergencia' => $data['emergency_contact'] ?? null,
            'VersionFila' => $version,
        ]);
    }

    /** @return array<string, mixed> */
    private static function fields(array $data): array
    {
        return [
            'TipoDocumento' => $data['document_type'],
            'NumeroDocumento' => $data['document_number'],
            'Nombres' => $data['first_names'],
            'Apellidos' => $data['last_names'],
            'FechaNacimiento' => $data['birth_date'] ?? null,
            'Sexo' => $data['sex'] ?? null,
            'Telefono' => $data['phone'] ?? null,
            'Email' => $data['email'] ?? null,
            'Direccion' => $data['address'] ?? null,
            'ContactoEmergencia' => $data['emergency_contact'] ?? null,
        ];
    }
}
