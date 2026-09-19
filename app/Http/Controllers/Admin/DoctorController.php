<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\DoctorAssignmentRequest;
use App\Http\Requests\DoctorRequest;
use App\Http\Requests\DoctorStatusRequest;
use App\Http\Requests\FilterRequest;
use App\Services\CatalogService;
use App\Services\DoctorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Médicos (§80): alta, edición, activar/desactivar, especialidades y sedes. Sin borrado físico. */
final class DoctorController extends Controller
{
    public function __construct(
        private readonly DoctorService $doctors,
        private readonly CatalogService $catalogs,
    ) {}

    public function index(FilterRequest $request): View
    {
        $f = $request->filters();

        return view('admin.doctors.index', [
            'page' => $this->doctors->search(
                $request->actor(),
                $f['q'] ?? null,
                isset($f['specialty_id']) ? (int) $f['specialty_id'] : null,
                isset($f['branch_id']) ? (int) $f['branch_id'] : null,
                isset($f['active']) ? (bool) $f['active'] : null,
                $request->page(),
                20,
            ),
            'filters' => $f,
            'specialties' => $this->catalogs->specialties(),
            'branches' => $this->catalogs->branches(),
        ]);
    }

    public function create(): View
    {
        return view('admin.doctors.form', [
            'doctor' => null,
            'specialties' => $this->catalogs->specialties(),
            'branches' => $this->catalogs->branches(),
        ]);
    }

    public function store(DoctorRequest $request): RedirectResponse
    {
        $result = $this->doctors->create($request->actor(), $request->validated());

        return redirect()->route('admin.doctors.edit', $result->entityId)->with('success', $result->userMessage());
    }

    public function edit(Request $request, int $doctor): View
    {
        return view('admin.doctors.form', [
            'doctor' => $this->doctors->find($request->user(), $doctor),
            'specialties' => $this->catalogs->specialties(),
            'branches' => $this->catalogs->branches(),
        ]);
    }

    public function update(DoctorRequest $request, int $doctor): RedirectResponse
    {
        $result = $this->doctors->update($request->actor(), $doctor, $request->validated());

        return redirect()->route('admin.doctors.edit', $doctor)->with('success', $result->userMessage());
    }

    public function status(DoctorStatusRequest $request, int $doctor): RedirectResponse
    {
        $data = $request->validated();
        $result = $this->doctors->changeStatus($request->actor(), $doctor, (bool) $data['active'], $data['version']);

        return back()->with('success', $result->userMessage());
    }

    public function assign(DoctorAssignmentRequest $request, int $doctor): RedirectResponse
    {
        $data = $request->validated();
        $result = $data['kind'] === 'specialty'
            ? $this->doctors->assignSpecialty($request->actor(), $doctor, (int) $data['target_id'], (bool) $data['assign'], (bool) ($data['main'] ?? false))
            : $this->doctors->assignBranch($request->actor(), $doctor, (int) $data['target_id'], (bool) $data['assign']);

        return redirect()->route('admin.doctors.edit', $doctor)->with('success', $result->userMessage());
    }
}
