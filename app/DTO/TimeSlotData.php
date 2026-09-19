<?php

namespace App\DTO;

use App\Support\Row;

/**
 * Horario (slot) de agenda para el wizard de reserva y la vista del día.
 * Estado visual: DISPONIBLE | BLOQUEADO | NO_DISPONIBLE (el estado "Seleccionado" es de la interfaz).
 */
final readonly class TimeSlotData
{
    public const AVAILABLE = 'DISPONIBLE';

    public const BLOCKED = 'BLOQUEADO';

    public const UNAVAILABLE = 'NO_DISPONIBLE';

    public function __construct(
        public int $id,
        public int $scheduleId,
        public int $doctorId,
        public string $doctorName,
        public string $cmp,
        public int $specialtyId,
        public string $specialty,
        public int $branchId,
        public string $branch,
        public string $room,
        public string $careType,
        public string $careTypeName,
        public string $date,
        public string $start,
        public string $end,
        public string $state = self::AVAILABLE,
    ) {}

    public static function fromRow(array $row): self
    {
        return new self(
            id: Row::int($row, 'HorarioMedicoId'),
            scheduleId: Row::int($row, 'AgendaId'),
            doctorId: Row::int($row, 'MedicoId'),
            doctorName: Row::str($row, 'MedicoNombre'),
            cmp: Row::str($row, 'CMP'),
            specialtyId: Row::int($row, 'EspecialidadId'),
            specialty: Row::str($row, 'Especialidad'),
            branchId: Row::int($row, 'SedeId'),
            branch: Row::str($row, 'Sede'),
            room: Row::str($row, 'Consultorio'),
            careType: Row::str($row, 'TipoAtencion'),
            careTypeName: Row::str($row, 'TipoAtencionNombre', ucfirst(strtolower(Row::str($row, 'TipoAtencion')))),
            date: Row::str($row, 'Fecha'),
            start: Row::time($row, 'HoraInicio'),
            end: Row::time($row, 'HoraFin'),
            state: Row::str($row, 'Estado', self::AVAILABLE),
        );
    }

    public function isAvailable(): bool
    {
        return $this->state === self::AVAILABLE;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
