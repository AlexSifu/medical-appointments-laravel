<?php

namespace App\Http\Controllers;

use App\Http\Requests\FilterRequest;
use App\Services\CatalogService;
use App\Services\DoctorService;
use Illuminate\View\View;

/** Directorio de médicos con filtros (§74): tarjetas con especialidad, sede y próxima agenda. */
final class DoctorDirectoryController extends Controller
{
    public function __construct(
        private readonly DoctorService $doctors,
        private readonly CatalogService $catalogs,
    ) {}

    public function __invoke(FilterRequest $request): View
    {
        $f = $request->filters();

        return view('doctors.directory', [
            'page' => $this->doctors->search(
                $request->actor(),
                $f['q'] ?? null,
                isset($f['specialty_id']) ? (int) $f['specialty_id'] : null,
                isset($f['branch_id']) ? (int) $f['branch_id'] : null,
                true,
                $request->page(),
            ),
            'specialties' => $this->catalogs->specialties(),
            'branches' => $this->catalogs->branches(),
            'filters' => $f,
        ]);
    }
}
