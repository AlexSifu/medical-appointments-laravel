<?php

namespace App\Repositories\SqlServer;

use App\DTO\AgendaData;
use App\DTO\BlockData;
use App\DTO\DaySlotData;
use App\DTO\PagedResult;
use App\DTO\ProcedureResult;
use App\DTO\TimeSlotData;
use App\Repositories\Contracts\ScheduleRepositoryInterface;

final class SqlServerScheduleRepository extends SqlServerRepository implements ScheduleRepositoryInterface
{
    private const AVAILABLE_DATES = 'api.usp_AgendaFechasDisponibles';

    private const AVAILABLE_SLOTS = 'api.usp_AgendaHorariosDisponibles';

    private const SLOT_STATES = 'api.usp_AgendaHorariosEstado';

    private const DOCTOR_DAY = 'api.usp_AgendaObtenerDia';

    private const OWN_AGENDA = 'api.usp_MedicoAgendaPropia';

    private const LIST_AGENDAS = 'api.usp_AdminAgendaListar';

    private const CREATE_AGENDA = 'api.usp_AdminAgendaCrear';

    private const GENERATE_RANGE = 'api.usp_AdminAgendaGenerarRango';

    private const DEACTIVATE_AGENDA = 'api.usp_AdminAgendaDesactivar';

    private const LIST_BLOCKS = 'api.usp_AdminBloqueosListar';

    private const BLOCK = 'api.usp_AdminHorarioBloquear';

    private const UNBLOCK = 'api.usp_AdminHorarioDesbloquear';

    public function availableDates(int $actorId, int $specialtyId, ?int $doctorId, ?int $branchId, string $from, string $to): array
    {
        return $this->sql->select(self::AVAILABLE_DATES, [
            'ActorUsuarioId' => $actorId,
            'EspecialidadId' => $specialtyId,
            'MedicoId' => $doctorId,
            'SedeId' => $branchId,
            'FechaDesde' => $from,
            'FechaHasta' => $to,
        ]);
    }

    public function availableSlots(int $actorId, int $specialtyId, string $date, ?int $doctorId, ?int $branchId): array
    {
        return array_map(TimeSlotData::fromRow(...), $this->sql->select(self::AVAILABLE_SLOTS, [
            'ActorUsuarioId' => $actorId,
            'EspecialidadId' => $specialtyId,
            'Fecha' => $date,
            'MedicoId' => $doctorId,
            'SedeId' => $branchId,
        ]));
    }

    public function slotStates(int $actorId, int $specialtyId, string $date, ?int $doctorId, ?int $branchId): array
    {
        return array_map(TimeSlotData::fromRow(...), $this->sql->select(self::SLOT_STATES, [
            'ActorUsuarioId' => $actorId,
            'EspecialidadId' => $specialtyId,
            'Fecha' => $date,
            'MedicoId' => $doctorId,
            'SedeId' => $branchId,
        ]));
    }

    public function doctorDay(int $actorId, int $doctorId, string $date): array
    {
        return array_map(DaySlotData::fromRow(...), $this->sql->select(self::DOCTOR_DAY, [
            'ActorUsuarioId' => $actorId,
            'MedicoId' => $doctorId,
            'Fecha' => $date,
        ]));
    }

    public function ownAgenda(int $actorId, string $from, string $to): array
    {
        return array_map(AgendaData::fromRow(...), $this->sql->select(self::OWN_AGENDA, [
            'ActorUsuarioId' => $actorId,
            'FechaDesde' => $from,
            'FechaHasta' => $to,
        ]));
    }

    public function listAgendas(int $actorId, ?int $doctorId, ?int $specialtyId, ?int $branchId, ?string $from, ?string $to, ?bool $active, int $page, int $perPage): PagedResult
    {
        return $this->paged(self::LIST_AGENDAS, [
            'ActorUsuarioId' => $actorId,
            'MedicoId' => $doctorId,
            'EspecialidadId' => $specialtyId,
            'SedeId' => $branchId,
            'FechaDesde' => $from,
            'FechaHasta' => $to,
            'Activo' => $active,
        ], $page, $perPage, AgendaData::fromRow(...));
    }

    public function createAgenda(int $actorId, array $data): ProcedureResult
    {
        return $this->sql->command(self::CREATE_AGENDA, ['ActorUsuarioId' => $actorId] + self::agendaFields($data) + [
            'Fecha' => $data['date'],
            'HoraInicio' => $data['start'],
            'HoraFin' => $data['end'],
            'DuracionMinutos' => (int) $data['duration'],
        ]);
    }

    public function generateRange(int $actorId, array $data): ProcedureResult
    {
        return $this->sql->command(self::GENERATE_RANGE, ['ActorUsuarioId' => $actorId] + self::agendaFields($data) + [
            'FechaDesde' => $data['date_from'],
            'FechaHasta' => $data['date_to'],
            'DiasSemana' => implode(',', array_map('intval', (array) $data['weekdays'])),
            'HoraInicio' => $data['start'],
            'HoraFin' => $data['end'],
            'DuracionMinutos' => (int) $data['duration'],
        ]);
    }

    public function deactivateAgenda(int $actorId, int $agendaId, string $version): ProcedureResult
    {
        return $this->sql->command(self::DEACTIVATE_AGENDA, [
            'ActorUsuarioId' => $actorId,
            'AgendaId' => $agendaId,
            'VersionFila' => $version,
        ]);
    }

    public function listBlocks(int $actorId, ?int $doctorId, ?string $from, ?string $to, bool $onlyActive, int $page, int $perPage): PagedResult
    {
        return $this->paged(self::LIST_BLOCKS, [
            'ActorUsuarioId' => $actorId,
            'MedicoId' => $doctorId,
            'FechaDesde' => $from,
            'FechaHasta' => $to,
            'SoloActivos' => $onlyActive,
        ], $page, $perPage, BlockData::fromRow(...));
    }

    public function block(int $actorId, array $data): ProcedureResult
    {
        return $this->sql->command(self::BLOCK, [
            'ActorUsuarioId' => $actorId,
            'MedicoId' => (int) $data['doctor_id'],
            'Fecha' => $data['date'] ?? null,
            'HoraInicio' => $data['start'] ?? null,
            'HoraFin' => $data['end'] ?? null,
            'TipoBloqueo' => $data['type'],
            'Motivo' => $data['reason'] ?? null,
            'HorarioMedicoId' => isset($data['slot_id']) ? (int) $data['slot_id'] : null,
        ]);
    }

    public function unblock(int $actorId, int $blockId): ProcedureResult
    {
        return $this->sql->command(self::UNBLOCK, ['ActorUsuarioId' => $actorId, 'BloqueoId' => $blockId]);
    }

    /** @return array<string, int> */
    private static function agendaFields(array $data): array
    {
        return [
            'MedicoId' => (int) $data['doctor_id'],
            'EspecialidadId' => (int) $data['specialty_id'],
            'SedeId' => (int) $data['branch_id'],
            'ConsultorioId' => (int) $data['room_id'],
            'TipoAtencionId' => (int) $data['care_type_id'],
        ];
    }
}
