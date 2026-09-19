<?php

namespace App\Services;

use App\DTO\AgendaData;
use App\DTO\BlockData;
use App\DTO\DaySlotData;
use App\DTO\PagedResult;
use App\DTO\ProcedureResult;
use App\DTO\TimeSlotData;
use App\Models\User;
use App\Repositories\Contracts\ScheduleRepositoryInterface;
use App\Support\LocalTime;

final class ScheduleService
{
    /** Horizonte de búsqueda del asistente; SQL Server limita además con MAX_DIAS_RESERVA_FUTURA. */
    public const SEARCH_DAYS = 60;

    public function __construct(private readonly ScheduleRepositoryInterface $schedules) {}

    /** @return list<array<string, mixed>> */
    public function availableDates(User $actor, int $specialtyId, ?int $doctorId, ?int $branchId): array
    {
        $from = LocalTime::today();

        return $this->schedules->availableDates(
            $actor->id(), $specialtyId, $doctorId, $branchId,
            $from->toDateString(), $from->copy()->addDays(self::SEARCH_DAYS)->toDateString(),
        );
    }

    /** @return list<TimeSlotData> */
    public function availableSlots(User $actor, int $specialtyId, string $date, ?int $doctorId, ?int $branchId): array
    {
        return $this->schedules->availableSlots($actor->id(), $specialtyId, $date, $doctorId, $branchId);
    }

    /** @return list<TimeSlotData> */
    public function slotStates(User $actor, int $specialtyId, string $date, ?int $doctorId, ?int $branchId): array
    {
        return $this->schedules->slotStates($actor->id(), $specialtyId, $date, $doctorId, $branchId);
    }

    /** @return list<DaySlotData> */
    public function doctorDay(User $actor, int $doctorId, string $date): array
    {
        return $this->schedules->doctorDay($actor->id(), $doctorId, $date);
    }

    /** @return list<AgendaData> */
    public function ownAgenda(User $actor, string $from, string $to): array
    {
        return $this->schedules->ownAgenda($actor->id(), $from, $to);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return PagedResult<AgendaData>
     */
    public function listAgendas(User $actor, array $filters, int $page, int $perPage = 20): PagedResult
    {
        return $this->schedules->listAgendas(
            $actor->id(),
            self::int($filters['doctor_id'] ?? null),
            self::int($filters['specialty_id'] ?? null),
            self::int($filters['branch_id'] ?? null),
            $filters['date_from'] ?? null ?: null,
            $filters['date_to'] ?? null ?: null,
            isset($filters['active']) && $filters['active'] !== '' ? (bool) $filters['active'] : null,
            $page, $perPage,
        );
    }

    public function createAgenda(User $actor, array $data): ProcedureResult
    {
        return $this->schedules->createAgenda($actor->id(), $data)->throwIfFailed();
    }

    public function generateRange(User $actor, array $data): ProcedureResult
    {
        return $this->schedules->generateRange($actor->id(), $data)->throwIfFailed();
    }

    public function deactivateAgenda(User $actor, int $agendaId, string $version): ProcedureResult
    {
        return $this->schedules->deactivateAgenda($actor->id(), $agendaId, $version)->throwIfFailed();
    }

    /** @return PagedResult<BlockData> */
    public function listBlocks(User $actor, array $filters, int $page, int $perPage = 20): PagedResult
    {
        return $this->schedules->listBlocks(
            $actor->id(),
            self::int($filters['doctor_id'] ?? null),
            $filters['date_from'] ?? null ?: null,
            $filters['date_to'] ?? null ?: null,
            ! empty($filters['only_active']),
            $page, $perPage,
        );
    }

    public function block(User $actor, array $data): ProcedureResult
    {
        return $this->schedules->block($actor->id(), $data)->throwIfFailed();
    }

    public function unblock(User $actor, int $blockId): ProcedureResult
    {
        return $this->schedules->unblock($actor->id(), $blockId)->throwIfFailed();
    }

    private static function int(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
