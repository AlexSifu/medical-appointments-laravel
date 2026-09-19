<?php

namespace App\Http\Controllers;

use App\Http\Requests\CancelReservationRequest;
use App\Http\Requests\CloseReservationRequest;
use App\Http\Requests\FilterRequest;
use App\Http\Requests\RescheduleReservationRequest;
use App\Models\Permission;
use App\Services\CatalogService;
use App\Services\ReservationService;
use App\Services\ScheduleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Operaciones sobre una reserva existente. El SP valida que el actor tenga acceso a esa
 * reserva (dueño, médico tratante o personal con reservas.gestionar) y el estado actual.
 */
final class ReservationController extends Controller
{
    public function __construct(
        private readonly ReservationService $reservations,
        private readonly ScheduleService $schedules,
        private readonly CatalogService $catalogs,
    ) {}

    /** Mis citas (§77): Próximas / Historial / Canceladas. */
    public function mine(FilterRequest $request): View
    {
        $tab = $request->validated('tab') ?? 'proximas';

        return view('patient.appointments', [
            'tab' => $tab,
            'page' => $this->reservations->mine($request->actor(), $tab, $request->page()),
            'reasons' => $this->reservations->cancellationReasons($request->actor()),
        ]);
    }

    public function show(Request $request, int $reservation): View
    {
        $user = $request->user();
        $detail = $this->reservations->detail($user, $reservation);

        return view('reservations.show', [
            'reservation' => $detail,
            'history' => $this->reservations->history($user, $reservation),
            'reasons' => $user->can(Permission::RESERVATIONS_CANCEL) ? $this->reservations->cancellationReasons($user) : [],
            'justConfirmed' => $request->boolean('confirmada'),
        ]);
    }

    public function cancel(CancelReservationRequest $request, int $reservation): RedirectResponse
    {
        $data = $request->validated();
        $result = $this->reservations->cancel($request->actor(), $reservation, (int) $data['reason_id'], $data['notes'] ?? null, $data['version']);

        return redirect()->route('reservations.show', $reservation)->with('success', $result->userMessage());
    }

    public function editReschedule(Request $request, int $reservation): View
    {
        $detail = $this->reservations->detail($request->user(), $reservation);
        abort_unless($detail->canReschedule, 403, 'Esta cita ya no puede reprogramarse.');

        return view('reservations.reschedule', [
            'reservation' => $detail,
            'branches' => $this->catalogs->branches(),
            'idempotencyKey' => (string) Str::uuid(),
            'searchDays' => ScheduleService::SEARCH_DAYS,
        ]);
    }

    public function reschedule(RescheduleReservationRequest $request, int $reservation): RedirectResponse
    {
        $data = $request->validated();
        $result = $this->reservations->reschedule(
            $request->actor(), $reservation, (int) $data['new_slot_id'], $data['reason'] ?? null, $data['version'], $data['idempotency_key'],
        );

        return redirect()
            ->route('reservations.show', ['reservation' => $result->entityId ?? $reservation, 'confirmada' => 1])
            ->with('success', $result->userMessage());
    }

    public function attended(CloseReservationRequest $request, int $reservation): RedirectResponse
    {
        $data = $request->validated();
        $result = $this->reservations->markAttended($request->actor(), $reservation, $data['note'] ?? null, $data['version']);

        return back()->with('success', $result->userMessage());
    }

    public function noShow(CloseReservationRequest $request, int $reservation): RedirectResponse
    {
        $data = $request->validated();
        $result = $this->reservations->markNoShow($request->actor(), $reservation, $data['note'] ?? null, $data['version']);

        return back()->with('success', $result->userMessage());
    }
}
