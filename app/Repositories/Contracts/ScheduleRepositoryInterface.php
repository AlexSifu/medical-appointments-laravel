<?php

namespace App\Repositories\Contracts;

use App\DTO\AgendaData;
use App\DTO\BlockData;
use App\DTO\DaySlotData;
use App\DTO\PagedResult;
use App\DTO\ProcedureResult;
use App\DTO\TimeSlotData;

interface ScheduleRepositoryInterface
{
    /** @return list<array<string, mixed>> Fecha, SlotsDisponibles, Medicos, PrimeraHora */
    public function availableDates(int $actorId, int $specialtyId, ?int $doctorId, ?int $branchId, string $from, string $to): array;

    /** @return list<TimeSlotData> solo horarios reservables */
    public function availableSlots(int $actorId, int $specialtyId, string $date, ?int $doctorId, ?int $branchId): array;

    /** @return list<TimeSlotData> horarios futuros con estado DISPONIBLE / BLOQUEADO / NO_DISPONIBLE */
    public function slotStates(int $actorId, int $specialtyId, string $date, ?int $doctorId, ?int $branchId): array;

    /** @return list<DaySlotData> */
    public function doctorDay(int $actorId, int $doctorId, string $date): array;

    /** @return list<AgendaData> */
    public function ownAgenda(int $actorId, string $from, string $to): array;

    /** @return PagedResult<AgendaData> */
    public function listAgendas(int $actorId, ?int $doctorId, ?int $specialtyId, ?int $branchId, ?string $from, ?string $to, ?bool $active, int $page, int $perPage): PagedResult;

    /** @param array<string, mixed> $data */
    public function createAgenda(int $actorId, array $data): ProcedureResult;

    /** @param array<string, mixed> $data */
    public function generateRange(int $actorId, array $data): ProcedureResult;

    public function deactivateAgenda(int $actorId, int $agendaId, string $version): ProcedureResult;

    /** @return PagedResult<BlockData> */
    public function listBlocks(int $actorId, ?int $doctorId, ?string $from, ?string $to, bool $onlyActive, int $page, int $perPage): PagedResult;

    /** @param array<string, mixed> $data */
    public function block(int $actorId, array $data): ProcedureResult;

    public function unblock(int $actorId, int $blockId): ProcedureResult;
}
