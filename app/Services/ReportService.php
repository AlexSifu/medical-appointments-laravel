<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\ReportRepositoryInterface;
use App\Support\LocalTime;

/** Reportes agregados (solo lectura). Los cálculos se hacen en SQL Server. */
final class ReportService
{
    public const REPORTS = [
        'especialidades' => 'Reservas por especialidad',
        'ocupacion' => 'Ocupación por médico',
        'cancelaciones' => 'Cancelaciones',
        'no-asistencia' => 'No asistencia',
    ];

    public function __construct(private readonly ReportRepositoryInterface $reports) {}

    /**
     * @param  array<string, mixed>  $filters  date_from, date_to, specialty_id, branch_id
     * @return array{from: string, to: string, rows: list<array<string, mixed>>}
     */
    public function run(User $actor, string $report, array $filters): array
    {
        $to = LocalTime::isDate($filters['date_to'] ?? null) ? $filters['date_to'] : LocalTime::today()->toDateString();
        $from = LocalTime::isDate($filters['date_from'] ?? null) ? $filters['date_from'] : LocalTime::today()->subDays(30)->toDateString();
        $specialty = is_numeric($filters['specialty_id'] ?? null) ? (int) $filters['specialty_id'] : null;
        $branch = is_numeric($filters['branch_id'] ?? null) ? (int) $filters['branch_id'] : null;

        $rows = match ($report) {
            'ocupacion' => $this->reports->doctorOccupancy($actor->id(), $from, $to, $specialty, $branch),
            'cancelaciones' => $this->reports->cancellations($actor->id(), $from, $to, $specialty),
            'no-asistencia' => $this->reports->noShows($actor->id(), $from, $to, $branch),
            default => $this->reports->reservationsBySpecialty($actor->id(), $from, $to, $branch),
        };

        return ['from' => $from, 'to' => $to, 'rows' => $rows];
    }
}
