<?php

namespace App\DTO;

use App\Support\Row;

/** Agenda diaria de un médico con sus contadores de slots (api.usp_AdminAgendaListar / api.usp_MedicoAgendaPropia). */
final readonly class AgendaData
{
    public function __construct(
        public int $id,
        public ?int $doctorId,
        public ?string $doctorName,
        public ?int $specialtyId,
        public string $specialty,
        public ?int $branchId,
        public string $branch,
        public string $room,
        public string $careType,
        public string $date,
        public string $start,
        public string $end,
        public int $durationMinutes,
        public bool $active,
        public string $version,
        public int $totalSlots,
        public int $bookedSlots,
        public int $blockedSlots,
        public int $freeSlots,
    ) {}

    public static function fromRow(array $row): self
    {
        return new self(
            id: Row::int($row, 'AgendaId'),
            doctorId: Row::intOrNull($row, 'MedicoId'),
            doctorName: Row::strOrNull($row, 'MedicoNombre'),
            specialtyId: Row::intOrNull($row, 'EspecialidadId'),
            specialty: Row::str($row, 'Especialidad'),
            branchId: Row::intOrNull($row, 'SedeId'),
            branch: Row::str($row, 'Sede'),
            room: Row::str($row, 'Consultorio'),
            careType: Row::str($row, 'TipoAtencion'),
            date: Row::str($row, 'Fecha'),
            start: Row::time($row, 'HoraInicio'),
            end: Row::time($row, 'HoraFin'),
            durationMinutes: Row::int($row, 'DuracionMinutos'),
            active: Row::bool($row, 'Activo', true),
            version: Row::str($row, 'VersionFila'),
            totalSlots: Row::int($row, 'TotalSlots'),
            bookedSlots: Row::int($row, 'SlotsOcupados'),
            blockedSlots: Row::int($row, 'SlotsBloqueados'),
            freeSlots: Row::int($row, 'SlotsLibres'),
        );
    }

    public function occupancyPercent(): int
    {
        $usable = $this->totalSlots - $this->blockedSlots;

        return $usable > 0 ? (int) round($this->bookedSlots * 100 / $usable) : 0;
    }
}
