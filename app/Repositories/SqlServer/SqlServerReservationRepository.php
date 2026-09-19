<?php

namespace App\Repositories\SqlServer;

use App\DTO\PagedResult;
use App\DTO\ProcedureResult;
use App\DTO\ReservationData;
use App\Repositories\Contracts\ReservationRepositoryInterface;

final class SqlServerReservationRepository extends SqlServerRepository implements ReservationRepositoryInterface
{
    private const CREATE = 'api.usp_ReservaCrear';

    private const CREATE_RECEPTION = 'api.usp_RecepcionReservaCrear';

    private const CANCEL = 'api.usp_ReservaCancelar';

    private const RESCHEDULE = 'api.usp_ReservaReprogramar';

    private const MARK_ATTENDED = 'api.usp_MedicoMarcarAtendida';

    private const MARK_NO_SHOW = 'api.usp_MedicoMarcarNoAsistio';

    private const MINE = 'api.usp_ReservaMisReservas';

    private const DETAIL = 'api.usp_ReservaObtenerDetalle';

    private const HISTORY = 'api.usp_ReservaHistorial';

    private const SEARCH = 'api.usp_AdminReservasBuscar';

    private const DOCTOR_DAY = 'api.usp_MedicoReservasDia';

    private const SEARCH_FILTERS = ['Texto', 'FechaDesde', 'FechaHasta', 'EstadoReservaId', 'MedicoId', 'EspecialidadId', 'SedeId', 'PacienteId'];

    public function create(int $actorId, ?int $patientId, int $slotId, ?string $notes, string $idempotencyKey): ProcedureResult
    {
        return $this->sql->command(self::CREATE, [
            'ActorUsuarioId' => $actorId,
            'PacienteId' => $patientId,
            'HorarioMedicoId' => $slotId,
            'Observacion' => self::text($notes),
            'IdempotencyKey' => $idempotencyKey,
        ]);
    }

    public function createForPatient(int $actorId, int $patientId, int $slotId, ?string $notes, string $idempotencyKey): ProcedureResult
    {
        return $this->sql->command(self::CREATE_RECEPTION, [
            'ActorUsuarioId' => $actorId,
            'PacienteId' => $patientId,
            'HorarioMedicoId' => $slotId,
            'Observacion' => self::text($notes),
            'IdempotencyKey' => $idempotencyKey,
        ]);
    }

    public function cancel(int $actorId, int $reservationId, int $reasonId, ?string $notes, string $version): ProcedureResult
    {
        return $this->sql->command(self::CANCEL, [
            'ActorUsuarioId' => $actorId,
            'ReservaId' => $reservationId,
            'MotivoCancelacionId' => $reasonId,
            'Observacion' => self::text($notes),
            'VersionFila' => $version,
        ]);
    }

    public function reschedule(int $actorId, int $reservationId, int $newSlotId, ?string $reason, string $version, string $idempotencyKey): ProcedureResult
    {
        return $this->sql->command(self::RESCHEDULE, [
            'ActorUsuarioId' => $actorId,
            'ReservaId' => $reservationId,
            'NuevoHorarioMedicoId' => $newSlotId,
            'Motivo' => self::text($reason),
            'VersionFila' => $version,
            'IdempotencyKey' => $idempotencyKey,
        ]);
    }

    public function markAttended(int $actorId, int $reservationId, ?string $note, string $version): ProcedureResult
    {
        return $this->close(self::MARK_ATTENDED, $actorId, $reservationId, $note, $version);
    }

    public function markNoShow(int $actorId, int $reservationId, ?string $note, string $version): ProcedureResult
    {
        return $this->close(self::MARK_NO_SHOW, $actorId, $reservationId, $note, $version);
    }

    public function mine(int $actorId, string $view, ?int $statusId, int $page, int $perPage): PagedResult
    {
        return $this->paged(self::MINE, [
            'ActorUsuarioId' => $actorId,
            'Vista' => in_array($view, ['PROXIMAS', 'HISTORIAL', 'TODAS'], true) ? $view : 'PROXIMAS',
            'EstadoReservaId' => $statusId,
        ], $page, $perPage, ReservationData::fromRow(...));
    }

    public function find(int $actorId, int $reservationId): ?ReservationData
    {
        $row = $this->sql->selectOne(self::DETAIL, ['ActorUsuarioId' => $actorId, 'ReservaId' => $reservationId]);

        return $row === null ? null : ReservationData::fromRow($row);
    }

    public function history(int $actorId, int $reservationId): array
    {
        return $this->sql->select(self::HISTORY, ['ActorUsuarioId' => $actorId, 'ReservaId' => $reservationId]);
    }

    public function search(int $actorId, array $filters, int $page, int $perPage): PagedResult
    {
        $params = ['ActorUsuarioId' => $actorId];
        foreach (self::SEARCH_FILTERS as $name) {
            $value = $filters[$name] ?? null;
            $params[$name] = $value === '' ? null : $value;
        }
        $params['Texto'] = self::text($params['Texto']);

        return $this->paged(self::SEARCH, $params, $page, $perPage, ReservationData::fromRow(...));
    }

    public function doctorDay(int $actorId, string $date, ?int $doctorId = null): array
    {
        return array_map(ReservationData::fromRow(...), $this->sql->select(self::DOCTOR_DAY, [
            'ActorUsuarioId' => $actorId,
            'Fecha' => $date,
            'MedicoId' => $doctorId,
        ]));
    }

    private function close(string $procedure, int $actorId, int $reservationId, ?string $note, string $version): ProcedureResult
    {
        return $this->sql->command($procedure, [
            'ActorUsuarioId' => $actorId,
            'ReservaId' => $reservationId,
            'Nota' => self::text($note),
            'VersionFila' => $version,
        ]);
    }
}
