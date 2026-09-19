<?php

namespace App\Http\Controllers;

use App\DTO\DoctorData;
use App\DTO\TimeSlotData;
use App\Http\Requests\CreateReservationRequest;
use App\Models\Permission;
use App\Services\CatalogService;
use App\Services\DoctorService;
use App\Services\PatientService;
use App\Services\ReservationService;
use App\Services\ScheduleService;
use App\Support\LocalTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Wizard de reserva (§73): Especialidad → Médico/Sede → Fecha → Horario → Confirmación.
 * Lo usan el paciente (para sí) y recepción (para un paciente elegido).
 * La disponibilidad y la reserva final las decide SQL Server; la UI solo guía.
 */
final class BookingController extends Controller
{
    public function __construct(
        private readonly CatalogService $catalogs,
        private readonly DoctorService $doctors,
        private readonly ScheduleService $schedules,
        private readonly ReservationService $reservations,
        private readonly PatientService $patients,
    ) {}

    public function create(Request $request): View
    {
        $user = $request->user();
        $patient = null;

        if ($user->can(Permission::RESERVATIONS_MANAGE)) {
            $patientId = filter_var($request->query('patient_id'), FILTER_VALIDATE_INT);
            $patient = $patientId ? $this->patients->find($user, $patientId) : null;
        }

        return view('booking.create', [
            'specialties' => $this->catalogs->specialties(),
            'branches' => $this->catalogs->branches(),
            'patient' => $patient,
            'staffBooking' => $user->can(Permission::RESERVATIONS_MANAGE),
            'preselectedSpecialty' => filter_var($request->query('specialty_id'), FILTER_VALIDATE_INT) ?: null,
            'preselectedDoctor' => filter_var($request->query('doctor_id'), FILTER_VALIDATE_INT) ?: null,
            'idempotencyKey' => (string) Str::uuid(),
            'searchDays' => ScheduleService::SEARCH_DAYS,
        ]);
    }

    /** Médicos activos de la especialidad (tarjetas §74). */
    public function doctors(Request $request): JsonResponse
    {
        [$specialtyId, $branchId] = $this->ids($request);

        $page = $this->doctors->search($request->user(), null, $specialtyId, $branchId, true, 1, 50);

        return response()->json(['data' => array_map(static fn (DoctorData $d): array => [
            'id' => $d->id,
            'name' => $d->fullName,
            'initials' => $d->initials(),
            'cmp' => $d->cmp,
            'specialties' => $d->specialties,
            'branches' => $d->branches,
            'next' => $d->nextAvailableDate ? LocalTime::date($d->nextAvailableDate) : null,
        ], $page->items)]);
    }

    /** Fechas con horarios libres en los próximos días. */
    public function dates(Request $request): JsonResponse
    {
        [$specialtyId, $branchId, $doctorId] = $this->ids($request);

        $rows = $this->schedules->availableDates($request->user(), $specialtyId, $doctorId, $branchId);

        return response()->json(['data' => array_map(static fn (array $r): array => [
            'date' => (string) $r['Fecha'],
            'label' => LocalTime::date((string) $r['Fecha']),
            'slots' => (int) $r['SlotsDisponibles'],
            'doctors' => (int) $r['Medicos'],
            'first' => LocalTime::time((string) $r['PrimeraHora']),
        ], $rows)]);
    }

    /** Horarios del día con su estado visual (§75). */
    public function slots(Request $request): JsonResponse
    {
        [$specialtyId, $branchId, $doctorId] = $this->ids($request);
        $date = (string) $request->query('date');
        abort_unless(LocalTime::isDate($date), 422, 'Fecha no válida.');

        $slots = $this->schedules->slotStates($request->user(), $specialtyId, $date, $doctorId, $branchId);

        return response()->json(['data' => array_map(static fn (TimeSlotData $s): array => [
            'id' => $s->id,
            'start' => $s->start,
            'end' => $s->end,
            'state' => $s->state,
            'doctor' => $s->doctorName,
            'doctorId' => $s->doctorId,
            'branch' => $s->branch,
            'room' => $s->room,
            'careType' => $s->careTypeName,
            'specialty' => $s->specialty,
            'date' => $s->date,
            'dateLabel' => LocalTime::longDate($s->date),
        ], $slots)]);
    }

    public function store(CreateReservationRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $result = $this->reservations->book(
            $request->actor(),
            (int) $data['slot_id'],
            isset($data['patient_id']) ? (int) $data['patient_id'] : null,
            $data['notes'] ?? null,
            $data['idempotency_key'],
        );

        return redirect()
            ->route('reservations.show', ['reservation' => $result->entityId, 'confirmada' => 1])
            ->with('success', $result->userMessage());
    }

    /** @return array{0:int,1:?int,2:?int} */
    private function ids(Request $request): array
    {
        $specialtyId = filter_var($request->query('specialty_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        abort_unless($specialtyId !== false, 422, 'Selecciona una especialidad.');

        $optional = static function (mixed $value): ?int {
            $int = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

            return $int === false ? null : $int;
        };

        return [$specialtyId, $optional($request->query('branch_id')), $optional($request->query('doctor_id'))];
    }
}
