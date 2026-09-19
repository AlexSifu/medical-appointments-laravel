<?php

namespace App\DTO;

use App\Support\Row;

/** Paciente (api.usp_PacienteBuscar / api.usp_PacienteObtener). */
final readonly class PatientData
{
    public function __construct(
        public int $id,
        public ?int $userId,
        public string $documentType,
        public string $documentNumber,
        public string $firstNames,
        public string $lastNames,
        public string $fullName,
        public ?string $birthDate,
        public ?string $sex,
        public ?string $phone,
        public ?string $email,
        public ?string $address,
        public ?string $emergencyContact,
        public bool $active,
        public string $version,
        public ?string $username,
        public int $confirmedReservations,
    ) {}

    public static function fromRow(array $row): self
    {
        return new self(
            id: Row::int($row, 'PacienteId'),
            userId: Row::intOrNull($row, 'UsuarioId'),
            documentType: Row::str($row, 'TipoDocumento'),
            documentNumber: Row::str($row, 'NumeroDocumento'),
            firstNames: Row::str($row, 'Nombres'),
            lastNames: Row::str($row, 'Apellidos'),
            fullName: Row::str($row, 'NombreCompleto', trim(Row::str($row, 'Nombres').' '.Row::str($row, 'Apellidos'))),
            birthDate: Row::strOrNull($row, 'FechaNacimiento'),
            sex: Row::strOrNull($row, 'Sexo'),
            phone: Row::strOrNull($row, 'Telefono'),
            email: Row::strOrNull($row, 'Email'),
            address: Row::strOrNull($row, 'Direccion'),
            emergencyContact: Row::strOrNull($row, 'ContactoEmergencia'),
            active: Row::bool($row, 'Activo', true),
            version: Row::str($row, 'VersionFila'),
            username: Row::strOrNull($row, 'NombreUsuario'),
            confirmedReservations: Row::int($row, 'ReservasConfirmadas'),
        );
    }

    public function document(): string
    {
        return $this->documentType.' '.$this->documentNumber;
    }
}
