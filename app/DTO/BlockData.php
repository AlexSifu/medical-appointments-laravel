<?php

namespace App\DTO;

use App\Support\Row;

/** Bloqueo de horario (api.usp_AdminBloqueosListar). */
final readonly class BlockData
{
    public function __construct(
        public int $id,
        public int $doctorId,
        public string $doctorName,
        public string $date,
        public string $start,
        public string $end,
        public string $type,
        public ?string $reason,
        public bool $active,
        public ?string $createdBy,
        public ?string $createdAtUtc,
        public ?string $liftedBy,
        public ?string $liftedAtUtc,
        public int $affectedSlots,
    ) {}

    public static function fromRow(array $row): self
    {
        return new self(
            id: Row::int($row, 'BloqueoId'),
            doctorId: Row::int($row, 'MedicoId'),
            doctorName: Row::str($row, 'MedicoNombre'),
            date: Row::str($row, 'Fecha'),
            start: Row::time($row, 'HoraInicio'),
            end: Row::time($row, 'HoraFin'),
            type: Row::str($row, 'TipoBloqueo'),
            reason: Row::strOrNull($row, 'Motivo'),
            active: Row::bool($row, 'Activo'),
            createdBy: Row::strOrNull($row, 'CreadoPor'),
            createdAtUtc: Row::strOrNull($row, 'FechaCreacionUtc'),
            liftedBy: Row::strOrNull($row, 'LevantadoPor'),
            liftedAtUtc: Row::strOrNull($row, 'FechaLevantamientoUtc'),
            affectedSlots: Row::int($row, 'SlotsAfectados'),
        );
    }
}
