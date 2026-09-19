<?php

namespace App\Http\Controllers\Admin;

use App\DTO\ReservationData;
use App\Http\Controllers\Controller;
use App\Http\Requests\FilterRequest;
use App\Models\User;
use App\Services\CatalogService;
use App\Services\ReservationService;
use App\Support\CsvExport;
use App\Support\LocalTime;
use Symfony\Component\HttpFoundation\Response;

/** Reservas (§83): filtros, paginación en SQL Server y exportación CSV opcional. */
final class ReservationAdminController extends Controller
{
    public function __construct(
        private readonly ReservationService $reservations,
        private readonly CatalogService $catalogs,
    ) {}

    public function index(FilterRequest $request): Response
    {
        $f = $request->filters();
        $filters = self::spFilters($f);

        if (($f['export'] ?? null) === 'csv') {
            return $this->csv($request->actor(), $filters);
        }

        return response()->view('admin.reservations.index', [
            'page' => $this->reservations->search($request->actor(), $filters, $request->page()),
            'filters' => $f,
            'specialties' => $this->catalogs->specialties(),
            'branches' => $this->catalogs->branches(),
            'statuses' => $this->catalogs->statuses(),
        ]);
    }

    /** @return array<string, mixed> Parámetros de api.usp_AdminReservasBuscar */
    private static function spFilters(array $f): array
    {
        return [
            'Texto' => $f['q'] ?? null,
            'FechaDesde' => $f['date_from'] ?? null,
            'FechaHasta' => $f['date_to'] ?? null,
            'EstadoReservaId' => $f['status_id'] ?? null,
            'MedicoId' => $f['doctor_id'] ?? null,
            'EspecialidadId' => $f['specialty_id'] ?? null,
            'SedeId' => $f['branch_id'] ?? null,
            'PacienteId' => $f['patient_id'] ?? null,
        ];
    }

    private function csv(User $actor, array $filters): Response
    {
        $rows = function () use ($actor, $filters) {
            $page = 1;
            $emitted = 0;
            do {
                $result = $this->reservations->search($actor, $filters, $page, 100);
                /** @var ReservationData $r */
                foreach ($result->items as $r) {
                    yield [
                        $r->code, LocalTime::date($r->date, 'd/m/Y'), $r->start, $r->end, $r->patientName, $r->patientDocument,
                        $r->doctorName, $r->specialty, $r->branch, $r->room, $r->statusName, $r->createdBy,
                    ];
                    if (++$emitted >= CsvExport::MAX_ROWS) {
                        return;
                    }
                }
                $page++;
            } while (($page - 1) * 100 < $result->total);
        };

        return CsvExport::download('reservas-'.LocalTime::today()->format('Ymd').'.csv', [
            'Código', 'Fecha', 'Inicio', 'Fin', 'Paciente', 'Documento', 'Médico', 'Especialidad', 'Sede', 'Consultorio', 'Estado', 'Registrado por',
        ], $rows());
    }
}
