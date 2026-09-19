<?php

namespace App\Http\Controllers\Reception;

use App\Http\Controllers\Controller;
use App\Http\Requests\FilterRequest;
use App\Http\Requests\PatientRequest;
use App\Services\PatientService;
use App\Services\ReservationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Pacientes para recepción: buscar, registrar, editar (sin borrado físico). */
final class PatientController extends Controller
{
    public function __construct(
        private readonly PatientService $patients,
        private readonly ReservationService $reservations,
    ) {}

    public function index(FilterRequest $request): View
    {
        $f = $request->filters();
        $active = isset($f['active']) ? (bool) $f['active'] : null;

        return view('reception.patients.index', [
            'page' => $this->patients->search($request->actor(), $f['q'] ?? null, $active, $request->page()),
            'filters' => $f,
        ]);
    }

    public function show(Request $request, int $patient): View
    {
        $user = $request->user();

        return view('reception.patients.show', [
            'patient' => $this->patients->find($user, $patient),
            'reservations' => $this->reservations->search($user, ['PacienteId' => $patient], 1, 20),
        ]);
    }

    public function create(): View
    {
        return view('reception.patients.form', ['patient' => null]);
    }

    public function store(PatientRequest $request): RedirectResponse
    {
        $result = $this->patients->create($request->actor(), $request->validated());

        return redirect()->route('reception.patients.show', $result->entityId)->with('success', $result->userMessage());
    }

    public function edit(Request $request, int $patient): View
    {
        return view('reception.patients.form', ['patient' => $this->patients->find($request->user(), $patient)]);
    }

    public function update(PatientRequest $request, int $patient): RedirectResponse
    {
        $result = $this->patients->update($request->actor(), $patient, $request->validated());

        return redirect()->route('reception.patients.show', $patient)->with('success', $result->userMessage());
    }
}
