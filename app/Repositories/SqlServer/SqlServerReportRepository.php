<?php

namespace App\Repositories\SqlServer;

use App\Repositories\Contracts\ReportRepositoryInterface;

final class SqlServerReportRepository extends SqlServerRepository implements ReportRepositoryInterface
{
    private const DASHBOARD = 'api.usp_DashboardResumen';

    private const DAILY_SERIES = 'api.usp_DashboardSerieDiaria';

    private const BY_SPECIALTY = 'api.usp_ReporteReservasPorEspecialidad';

    private const OCCUPANCY = 'api.usp_ReporteOcupacionMedicos';

    private const CANCELLATIONS = 'api.usp_ReporteCancelaciones';

    private const NO_SHOWS = 'api.usp_ReporteNoAsistencia';

    public function dashboardSummary(int $actorId): array
    {
        $row = $this->sql->selectOne(self::DASHBOARD, ['ActorUsuarioId' => $actorId]) ?? [];

        return array_map('intval', $row);
    }

    public function dailySeries(int $actorId): array
    {
        return $this->sql->select(self::DAILY_SERIES, ['ActorUsuarioId' => $actorId]);
    }

    public function reservationsBySpecialty(int $actorId, string $from, string $to, ?int $branchId): array
    {
        return $this->sql->select(self::BY_SPECIALTY, [
            'ActorUsuarioId' => $actorId, 'FechaDesde' => $from, 'FechaHasta' => $to, 'SedeId' => $branchId,
        ]);
    }

    public function doctorOccupancy(int $actorId, string $from, string $to, ?int $specialtyId, ?int $branchId): array
    {
        return $this->sql->select(self::OCCUPANCY, [
            'ActorUsuarioId' => $actorId, 'FechaDesde' => $from, 'FechaHasta' => $to,
            'EspecialidadId' => $specialtyId, 'SedeId' => $branchId,
        ]);
    }

    public function cancellations(int $actorId, string $from, string $to, ?int $specialtyId): array
    {
        return $this->sql->select(self::CANCELLATIONS, [
            'ActorUsuarioId' => $actorId, 'FechaDesde' => $from, 'FechaHasta' => $to, 'EspecialidadId' => $specialtyId,
        ]);
    }

    public function noShows(int $actorId, string $from, string $to, ?int $branchId): array
    {
        return $this->sql->select(self::NO_SHOWS, [
            'ActorUsuarioId' => $actorId, 'FechaDesde' => $from, 'FechaHasta' => $to, 'SedeId' => $branchId,
        ]);
    }
}
