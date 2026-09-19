<?php

namespace App\DTO;

use App\Models\ReservationStatus;
use App\Support\Row;

/**
 * Reserva (cita). Se construye desde cualquiera de los SP de reservas; las columnas
 * ausentes en un listado quedan en null. Los flags Puede* los calcula SQL Server.
 */
final readonly class ReservationData
{
    public function __construct(
        public int $id,
        public string $code,
        public ?int $slotId,
        public ?int $patientId,
        public ?string $patientName,
        public ?string $patientDocument,
        public ?int $patientUserId,
        public ?int $doctorId,
        public string $doctorName,
        public ?string $cmp,
        public ?int $doctorUserId,
        public ?int $specialtyId,
        public string $specialty,
        public ?int $branchId,
        public string $branch,
        public ?string $branchAddress,
        public string $room,
        public string $careType,
        public string $date,
        public string $start,
        public string $end,
        public int $statusId,
        public string $statusCode,
        public string $statusName,
        public ?string $notes,
        public ?string $cancellationReason,
        public ?string $cancellationNotes,
        public ?string $cancelledAtUtc,
        public ?string $originCode,
        public ?string $replacementCode,
        public ?int $replacementId,
        public ?string $createdBy,
        public ?string $createdAtUtc,
        public string $version,
        public bool $canCancel,
        public bool $canReschedule,
        public bool $canClose,
        public ?int $minimumCancellationHours,
    ) {}

    public static function fromRow(array $row): self
    {
        $canModify = Row::bool($row, 'PuedeModificar');

        return new self(
            id: Row::int($row, 'ReservaId'),
            code: Row::str($row, 'CodigoReserva'),
            slotId: Row::intOrNull($row, 'HorarioMedicoId'),
            patientId: Row::intOrNull($row, 'PacienteId'),
            patientName: Row::strOrNull($row, 'PacienteNombre'),
            patientDocument: Row::strOrNull($row, 'PacienteDocumento'),
            patientUserId: Row::intOrNull($row, 'PacienteUsuarioId'),
            doctorId: Row::intOrNull($row, 'MedicoId'),
            doctorName: Row::str($row, 'MedicoNombre'),
            cmp: Row::strOrNull($row, 'CMP'),
            doctorUserId: Row::intOrNull($row, 'MedicoUsuarioId'),
            specialtyId: Row::intOrNull($row, 'EspecialidadId'),
            specialty: Row::str($row, 'Especialidad'),
            branchId: Row::intOrNull($row, 'SedeId'),
            branch: Row::str($row, 'Sede'),
            branchAddress: Row::strOrNull($row, 'SedeDireccion'),
            room: Row::str($row, 'Consultorio'),
            careType: Row::str($row, 'TipoAtencion'),
            date: Row::str($row, 'FechaCita'),
            start: Row::time($row, 'HoraInicio'),
            end: Row::time($row, 'HoraFin'),
            statusId: Row::int($row, 'EstadoReservaId'),
            statusCode: Row::str($row, 'EstadoCodigo'),
            statusName: Row::str($row, 'EstadoNombre'),
            notes: Row::strOrNull($row, 'Observacion'),
            cancellationReason: Row::strOrNull($row, 'MotivoCancelacion'),
            cancellationNotes: Row::strOrNull($row, 'ObservacionCancelacion'),
            cancelledAtUtc: Row::strOrNull($row, 'FechaCancelacionUtc'),
            originCode: Row::strOrNull($row, 'CodigoReservaOrigen'),
            replacementCode: Row::strOrNull($row, 'CodigoReservaReemplazo'),
            replacementId: Row::intOrNull($row, 'ReservaReemplazoId'),
            createdBy: Row::strOrNull($row, 'CreadoPor'),
            createdAtUtc: Row::strOrNull($row, 'FechaCreacionUtc'),
            version: Row::str($row, 'VersionFila', Row::str($row, 'ReservaVersionFila')),
            canCancel: Row::bool($row, 'PuedeCancelar', $canModify),
            canReschedule: Row::bool($row, 'PuedeReprogramar', $canModify),
            canClose: Row::bool($row, 'PuedeCerrar'),
            minimumCancellationHours: Row::intOrNull($row, 'HorasMinimasCancelacion'),
        );
    }

    public function isConfirmed(): bool
    {
        return $this->statusId === ReservationStatus::CONFIRMED;
    }

    public function careTypeName(): string
    {
        return ucfirst(mb_strtolower($this->careType));
    }
}
