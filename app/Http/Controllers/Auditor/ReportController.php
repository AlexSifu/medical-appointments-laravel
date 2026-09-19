<?php

namespace App\Http\Controllers\Auditor;

use App\Http\Controllers\Controller;
use App\Http\Requests\FilterRequest;
use App\Services\CatalogService;
use App\Services\ReportService;
use App\Support\CsvExport;
use Symfony\Component\HttpFoundation\Response;

/** Reportes (§85): agregados calculados en SQL Server, con gráfico y CSV. */
final class ReportController extends Controller
{
    /** Columnas visibles por reporte: columna del SP => encabezado. */
    public const COLUMNS = [
        'especialidades' => [
            'Especialidad' => 'Especialidad', 'Total' => 'Total', 'Confirmadas' => 'Confirmadas', 'Atendidas' => 'Atendidas',
            'NoAsistio' => 'No asistió', 'Canceladas' => 'Canceladas', 'Reprogramadas' => 'Reprogramadas',
        ],
        'ocupacion' => [
            'MedicoNombre' => 'Médico', 'CMP' => 'CMP', 'SlotsTotales' => 'Horarios', 'SlotsBloqueados' => 'Bloqueados',
            'SlotsOcupados' => 'Ocupados', 'Atendidas' => 'Atendidas', 'NoAsistio' => 'No asistió', 'OcupacionPct' => 'Ocupación %',
        ],
        'cancelaciones' => [
            'Motivo' => 'Motivo', 'Total' => 'Total', 'PorPaciente' => 'Por paciente', 'PorPersonal' => 'Por personal',
            'HorasAnticipacionPromedio' => 'Anticipación prom. (h)',
        ],
        'no-asistencia' => [
            'Especialidad' => 'Especialidad', 'Atendidas' => 'Atendidas', 'NoAsistio' => 'No asistió', 'TasaNoAsistenciaPct' => 'No asistencia %',
        ],
    ];

    /** Columna etiqueta y columna valor para el gráfico. */
    public const CHART = [
        'especialidades' => ['Especialidad', 'Total'],
        'ocupacion' => ['MedicoNombre', 'OcupacionPct'],
        'cancelaciones' => ['Motivo', 'Total'],
        'no-asistencia' => ['Especialidad', 'TasaNoAsistenciaPct'],
    ];

    public function __construct(
        private readonly ReportService $reports,
        private readonly CatalogService $catalogs,
    ) {}

    public function __invoke(FilterRequest $request, string $report = 'especialidades'): Response
    {
        abort_unless(isset(ReportService::REPORTS[$report]), 404);

        $f = $request->filters();
        $result = $this->reports->run($request->actor(), $report, $f);
        $columns = self::COLUMNS[$report];

        if (($f['export'] ?? null) === 'csv') {
            $rows = array_map(static fn (array $row): array => array_map(static fn (string $c) => $row[$c] ?? null, array_keys($columns)), $result['rows']);

            return CsvExport::download("reporte-{$report}-{$result['from']}-{$result['to']}.csv", array_values($columns), $rows);
        }

        [$labelCol, $valueCol] = self::CHART[$report];

        return response()->view('auditor.reports', [
            'report' => $report,
            'reports' => ReportService::REPORTS,
            'columns' => $columns,
            'result' => $result,
            'filters' => $f,
            'chart' => [
                'labels' => array_map(static fn (array $r): string => (string) $r[$labelCol], $result['rows']),
                'values' => array_map(static fn (array $r): float => (float) $r[$valueCol], $result['rows']),
                'label' => $columns[$valueCol],
            ],
            'specialties' => $this->catalogs->specialties(),
            'branches' => $this->catalogs->branches(),
        ]);
    }
}
