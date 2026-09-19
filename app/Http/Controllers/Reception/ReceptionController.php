<?php

namespace App\Http\Controllers\Reception;

use App\Http\Controllers\Controller;
use App\Http\Requests\FilterRequest;
use App\Services\CatalogService;
use App\Services\ReservationService;
use App\Support\LocalTime;
use Illuminate\View\View;

/** Recepción (§78): citas del día con filtros por médico, especialidad, sede y estado. */
final class ReceptionController extends Controller
{
    public function __construct(
        private readonly ReservationService $reservations,
        private readonly CatalogService $catalogs,
    ) {}

    public function __invoke(FilterRequest $request): View
    {
        $f = $request->filters();
        $date = $f['date'] ?? LocalTime::today()->toDateString();

        $page = $this->reservations->search($request->actor(), [
            'Texto' => $f['q'] ?? null,
            'FechaDesde' => $date,
            'FechaHasta' => $date,
            'EstadoReservaId' => $f['status_id'] ?? null,
            'EspecialidadId' => $f['specialty_id'] ?? null,
            'SedeId' => $f['branch_id'] ?? null,
        ], $request->page(), 50);

        return view('reception.index', [
            'date' => $date,
            'page' => $page,
            'filters' => $f,
            'specialties' => $this->catalogs->specialties(),
            'branches' => $this->catalogs->branches(),
            'statuses' => $this->catalogs->statuses(),
            'reasons' => $this->reservations->cancellationReasons($request->actor()),
        ]);
    }
}
