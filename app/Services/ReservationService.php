<?php

namespace App\Services;

use App\DTO\PagedResult;
use App\DTO\ProcedureResult;
use App\DTO\ReservationData;
use App\Exceptions\NotFoundException;
use App\Models\Permission;
use App\Models\User;
use App\Repositories\Contracts\CatalogRepositoryInterface;
use App\Repositories\Contracts\ReservationRepositoryInterface;
use Illuminate\Support\Str;

/**
 * Casos de uso de reservas. Las reglas (disponibilidad, solapes, plazos, permisos por dueño,
 * concurrencia, idempotencia) las aplica SQL Server; aquí solo se orquesta y se traduce el resultado.
 */
final class ReservationService
{
    public function __construct(
        private readonly ReservationRepositoryInterface $reservations,
        private readonly CatalogRepositoryInterface $catalogs,
    ) {}

    /** Paciente reservando para sí mismo, o personal con reservas.gestionar para un paciente. */
    public function book(User $actor, int $slotId, ?int $patientId, ?string $notes, ?string $idempotencyKey): ProcedureResult
    {
        $key = self::key($idempotencyKey);

        if ($patientId !== null && $actor->can(Permission::RESERVATIONS_MANAGE)) {
            return $this->reservations->createForPatient($actor->id(), $patientId, $slotId, $notes, $key)->throwIfFailed();
        }

        return $this->reservations->create($actor->id(), null, $slotId, $notes, $key)->throwIfFailed();
    }

    public function cancel(User $actor, int $reservationId, int $reasonId, ?string $notes, string $version): ProcedureResult
    {
        return $this->reservations->cancel($actor->id(), $reservationId, $reasonId, $notes, $version)->throwIfFailed();
    }

    public function reschedule(User $actor, int $reservationId, int $newSlotId, ?string $reason, string $version, ?string $idempotencyKey): ProcedureResult
    {
        return $this->reservations
            ->reschedule($actor->id(), $reservationId, $newSlotId, $reason, $version, self::key($idempotencyKey))
            ->throwIfFailed();
    }

    public function markAttended(User $actor, int $reservationId, ?string $note, string $version): ProcedureResult
    {
        return $this->reservations->markAttended($actor->id(), $reservationId, $note, $version)->throwIfFailed();
    }

    public function markNoShow(User $actor, int $reservationId, ?string $note, string $version): ProcedureResult
    {
        return $this->reservations->markNoShow($actor->id(), $reservationId, $note, $version)->throwIfFailed();
    }

    /**
     * Pestañas de "Mis citas": proximas | historial | canceladas.
     *
     * @return PagedResult<ReservationData>
     */
    public function mine(User $actor, string $tab, int $page, int $perPage = 10): PagedResult
    {
        return match ($tab) {
            'historial' => $this->reservations->mine($actor->id(), 'HISTORIAL', null, $page, $perPage),
            'canceladas' => $this->reservations->mine($actor->id(), 'TODAS', 2, $page, $perPage),
            default => $this->reservations->mine($actor->id(), 'PROXIMAS', null, $page, $perPage),
        };
    }

    /** @throws NotFoundException */
    public function detail(User $actor, int $reservationId): ReservationData
    {
        return $this->reservations->find($actor->id(), $reservationId)
            ?? throw new NotFoundException('La cita no existe o no tienes acceso a ella.');
    }

    /** @return list<array<string, mixed>> */
    public function history(User $actor, int $reservationId): array
    {
        return $this->reservations->history($actor->id(), $reservationId);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return PagedResult<ReservationData>
     */
    public function search(User $actor, array $filters, int $page, int $perPage = 20): PagedResult
    {
        return $this->reservations->search($actor->id(), $filters, $page, $perPage);
    }

    /** @return list<ReservationData> */
    public function doctorDay(User $actor, string $date, ?int $doctorId = null): array
    {
        return $this->reservations->doctorDay($actor->id(), $date, $doctorId);
    }

    /** Motivos visibles para el actor: los exclusivos del personal solo si gestiona reservas. */
    public function cancellationReasons(User $actor): array
    {
        return $this->catalogs->cancellationReasons($actor->can(Permission::RESERVATIONS_MANAGE));
    }

    private static function key(?string $key): string
    {
        return $key !== null && Str::isUuid($key) ? strtolower($key) : (string) Str::uuid();
    }
}
