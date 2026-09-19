<?php

namespace App\DTO;

use App\Support\Row;

/** Cuenta de usuario para administración (api.usp_AdminUsuariosBuscar / api.usp_AdminUsuarioObtener). Nunca incluye el hash. */
final readonly class UserAccountData
{
    /** @param list<string> $roles */
    public function __construct(
        public int $id,
        public string $username,
        public ?string $email,
        public string $firstNames,
        public string $lastNames,
        public bool $active,
        public array $roles,
        public string $passwordAlgorithm,
        public ?string $lockedUntilUtc,
        public ?string $lastAccessUtc,
        public ?string $createdAtUtc,
        public string $version,
        public ?int $doctorId,
        public ?int $patientId,
    ) {}

    public static function fromRow(array $row): self
    {
        return new self(
            id: Row::int($row, 'UsuarioId'),
            username: Row::str($row, 'NombreUsuario'),
            email: Row::strOrNull($row, 'Email'),
            firstNames: Row::str($row, 'Nombres'),
            lastNames: Row::str($row, 'Apellidos'),
            active: Row::bool($row, 'Activo', true),
            roles: Row::list($row, 'Roles'),
            passwordAlgorithm: Row::str($row, 'PasswordAlgoritmo'),
            lockedUntilUtc: Row::strOrNull($row, 'BloqueadoHastaUtc'),
            lastAccessUtc: Row::strOrNull($row, 'UltimoAccesoUtc'),
            createdAtUtc: Row::strOrNull($row, 'FechaCreacionUtc'),
            version: Row::str($row, 'VersionFila'),
            doctorId: Row::intOrNull($row, 'MedicoId'),
            patientId: Row::intOrNull($row, 'PacienteId'),
        );
    }

    public function fullName(): string
    {
        return trim($this->firstNames.' '.$this->lastNames);
    }
}
