<?php

namespace App\Http\Controllers\Admin;

use App\DTO\DoctorData;
use App\Http\Controllers\Controller;
use App\Http\Requests\BlockRequest;
use App\Http\Requests\FilterRequest;
use App\Http\Requests\ScheduleRequest;
use App\Models\User;
use App\Services\CatalogService;
use App\Services\DoctorService;
use App\Services\ScheduleService;
use App\Support\LocalTime;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Agenda (§81): agendas por día o rango, vista del día del médico, bloqueos. */
final class ScheduleController extends Controller
{
    public function __construct(
        private readonly ScheduleService $schedules,
        private readonly DoctorService $doctors,
        private readonly CatalogService $catalogs,
    ) {}

    public function index(FilterRequest $request): View
    {
        $f = $request->filters() + ['date_from' => LocalTime::today()->toDateString()];

        return view('admin.schedules.index', [
            'page' => $this->schedules->listAgendas($request->actor(), $f, $request->page()),
            'filters' => $f,
        ] + $this->formOptions($request->actor()));
    }

    public function create(Request $request): View
    {
        return view('admin.schedules.form', $this->formOptions($request->user()));
    }

    public function store(ScheduleRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $result = $data['mode'] === 'range'
            ? $this->schedules->generateRange($request->actor(), $data)
            : $this->schedules->createAgenda($request->actor(), $data);

        return redirect()->route('admin.schedules.index', ['doctor_id' => $data['doctor_id']])->with('success', $result->userMessage());
    }

    public function deactivate(Request $request, int $agenda): RedirectResponse
    {
        $version = (string) $request->input('version');
        abort_unless(preg_match('/^0x[0-9A-Fa-f]{16}$/', $version) === 1, 422, 'Versión de registro no válida.');

        $result = $this->schedules->deactivateAgenda($request->user(), $agenda, $version);

        return back()->with('success', $result->userMessage());
    }

    /** Día de un médico con el estado de cada horario (para bloquear o revisar). */
    public function day(FilterRequest $request): View
    {
        $f = $request->filters();
        $date = $f['date'] ?? LocalTime::today()->toDateString();
        $doctorId = isset($f['doctor_id']) ? (int) $f['doctor_id'] : null;

        return view('admin.schedules.day', [
            'date' => $date,
            'doctorId' => $doctorId,
            'slots' => $doctorId ? $this->schedules->doctorDay($request->actor(), $doctorId, $date) : [],
            'blockTypes' => BlockRequest::TYPES,
        ] + $this->formOptions($request->actor()));
    }

    public function blocks(FilterRequest $request): View
    {
        $f = $request->filters() + ['only_active' => true];

        return view('admin.schedules.blocks', [
            'page' => $this->schedules->listBlocks($request->actor(), $f, $request->page()),
            'filters' => $f,
            'blockTypes' => BlockRequest::TYPES,
        ] + $this->formOptions($request->actor()));
    }

    public function block(BlockRequest $request): RedirectResponse
    {
        $result = $this->schedules->block($request->actor(), $request->validated());

        return back()->with('success', $result->userMessage());
    }

    public function unblock(Request $request, int $block): RedirectResponse
    {
        $result = $this->schedules->unblock($request->user(), $block);

        return back()->with('success', $result->userMessage());
    }

    /** @return array<string, mixed> */
    private function formOptions(User $actor): array
    {
        $doctors = $this->doctors->search($actor, null, null, null, true, 1, 100)->items;

        return [
            'doctors' => array_map(static fn (DoctorData $d): array => [
                'id' => $d->id,
                'name' => $d->fullName,
                'specialtyIds' => $d->specialtyIds,
                'branchIds' => $d->branchIds,
            ], $doctors),
            'specialties' => $this->catalogs->specialties(),
            'branches' => $this->catalogs->branches(),
            'rooms' => $this->catalogs->rooms(),
            'careTypes' => $this->catalogs->careTypes(),
        ];
    }
}
