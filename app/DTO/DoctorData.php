<?php

namespace App\DTO;

use App\Support\Row;

/** Médico del catálogo (api.usp_MedicosBuscar / api.usp_MedicoObtener). */
final readonly class DoctorData
{
    /**
     * @param  list<string>  $specialties
     * @param  list<int>  $specialtyIds
     * @param  list<string>  $branches
     * @param  list<int>  $branchIds
     */
    public function __construct(
        public int $id,
        public ?int $userId,
        public string $cmp,
        public string $firstNames,
        public string $lastNames,
        public string $fullName,
        public ?string $phone,
        public ?string $email,
        public bool $active,
        public string $version,
        public ?int $mainSpecialtyId,
        public ?string $mainSpecialty,
        public array $specialties,
        public array $specialtyIds,
        public array $branches,
        public array $branchIds,
        public ?string $nextAvailableDate = null,
    ) {}

    public static function fromRow(array $row): self
    {
        return new self(
            id: Row::int($row, 'MedicoId'),
            userId: Row::intOrNull($row, 'UsuarioId'),
            cmp: Row::str($row, 'CMP'),
            firstNames: Row::str($row, 'Nombres'),
            lastNames: Row::str($row, 'Apellidos'),
            fullName: Row::str($row, 'NombreCompleto', trim(Row::str($row, 'Nombres').' '.Row::str($row, 'Apellidos'))),
            phone: Row::strOrNull($row, 'Telefono'),
            email: Row::strOrNull($row, 'Email'),
            active: Row::bool($row, 'Activo', true),
            version: Row::str($row, 'VersionFila'),
            mainSpecialtyId: Row::intOrNull($row, 'EspecialidadPrincipalId'),
            mainSpecialty: Row::strOrNull($row, 'EspecialidadPrincipal'),
            specialties: Row::list($row, 'Especialidades'),
            specialtyIds: Row::intList($row, 'EspecialidadIds'),
            branches: Row::list($row, 'Sedes'),
            branchIds: Row::intList($row, 'SedeIds'),
            nextAvailableDate: Row::strOrNull($row, 'ProximaAgenda'),
        );
    }

    public function initials(): string
    {
        return mb_strtoupper(mb_substr($this->firstNames, 0, 1).mb_substr($this->lastNames, 0, 1));
    }
}
