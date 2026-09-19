<?php

namespace App\DTO;

use App\Support\Row;

/** Slot de la agenda diaria de un médico (api.usp_AgendaObtenerDia): libre, bloqueado o con reserva. */
final readonly class DaySlotData
{
    public function __construct(
        public int $slotId,
        public string $date,
        public string $start,
        public string $end,
        public string $specialty,
        public string $branch,
        public string $room,
        public string $careType,
        public bool $blocked,
        public ?string $blockType,
        public ?string $blockReason,
        public ?int $blockId,
        public ?int $reservationId,
        public ?string $reservationCode,
        public ?int $statusId,
        public ?string $statusCode,
        public ?string $statusName,
        public ?string $patientName,
        public ?string $patientDocument,
        public ?string $notes,
        public ?string $reservationVersion,
    ) {}

    public static function fromRow(array $row): self
    {
        return new self(
            slotId: Row::int($row, 'HorarioMedicoId'),
            date: Row::str($row, 'Fecha'),
            start: Row::time($row, 'HoraInicio'),
            end: Row::time($row, 'HoraFin'),
            specialty: Row::str($row, 'Especialidad'),
            branch: Row::str($row, 'Sede'),
            room: Row::str($row, 'Consultorio'),
            careType: Row::str($row, 'TipoAtencion'),
            blocked: Row::bool($row, 'Bloqueado'),
            blockType: Row::strOrNull($row, 'TipoBloqueo'),
            blockReason: Row::strOrNull($row, 'MotivoBloqueo'),
            blockId: Row::intOrNull($row, 'BloqueoId'),
            reservationId: Row::intOrNull($row, 'ReservaId'),
            reservationCode: Row::strOrNull($row, 'CodigoReserva'),
            statusId: Row::intOrNull($row, 'EstadoReservaId'),
            statusCode: Row::strOrNull($row, 'EstadoCodigo'),
            statusName: Row::strOrNull($row, 'EstadoNombre'),
            patientName: Row::strOrNull($row, 'PacienteNombre'),
            patientDocument: Row::strOrNull($row, 'PacienteDocumento'),
            notes: Row::strOrNull($row, 'Observacion'),
            reservationVersion: Row::strOrNull($row, 'ReservaVersionFila'),
        );
    }

    public function isFree(): bool
    {
        return ! $this->blocked && $this->reservationId === null;
    }
}
