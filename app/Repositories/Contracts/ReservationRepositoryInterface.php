<?php

namespace App\Repositories\Contracts;

use App\DTO\PagedResult;
use App\DTO\ProcedureResult;
use App\DTO\ReservationData;

interface ReservationRepositoryInterface
{
    /** Reserva propia (paciente: $patientId null) o de personal con reservas.gestionar. */
    public function create(int $actorId, ?int $patientId, int $slotId, ?string $notes, string $idempotencyKey): ProcedureResult;

    /** Reserva creada por recepción para un paciente. */
    public function createForPatient(int $actorId, int $patientId, int $slotId, ?string $notes, string $idempotencyKey): ProcedureResult;

    public function cancel(int $actorId, int $reservationId, int $reasonId, ?string $notes, string $version): ProcedureResult;

    public function reschedule(int $actorId, int $reservationId, int $newSlotId, ?string $reason, string $version, string $idempotencyKey): ProcedureResult;

    public function markAttended(int $actorId, int $reservationId, ?string $note, string $version): ProcedureResult;

    public function markNoShow(int $actorId, int $reservationId, ?string $note, string $version): ProcedureResult;

    /**
     * @param  string  $view  PROXIMAS | HISTORIAL | TODAS
     * @return PagedResult<ReservationData>
     */
    public function mine(int $actorId, string $view, ?int $statusId, int $page, int $perPage): PagedResult;

    public function find(int $actorId, int $reservationId): ?ReservationData;

    /** @return list<array<string, mixed>> */
    public function history(int $actorId, int $reservationId): array;

    /**
     * @param  array<string, mixed>  $filters  Texto, FechaDesde, FechaHasta, EstadoReservaId, MedicoId, EspecialidadId, SedeId, PacienteId
     * @return PagedResult<ReservationData>
     */
    public function search(int $actorId, array $filters, int $page, int $perPage): PagedResult;

    /** @return list<ReservationData> */
    public function doctorDay(int $actorId, string $date, ?int $doctorId = null): array;
}
