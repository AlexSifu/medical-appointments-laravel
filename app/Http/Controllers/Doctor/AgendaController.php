<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Http\Requests\FilterRequest;
use App\Models\ReservationStatus;
use App\Services\ReservationService;
use App\Services\ScheduleService;
use App\Support\LocalTime;
use Illuminate\View\View;

/** Panel del médico (§79): citas del día y agenda propia de las próximas semanas. */
final class AgendaController extends Controller
{
    public function __construct(
        private readonly ReservationService $reservations,
        private readonly ScheduleService $schedules,
    ) {}

    public function day(FilterRequest $request): View
    {
        $date = $request->validated('date') ?? LocalTime::today()->toDateString();
        $items = $this->reservations->doctorDay($request->actor(), $date);

        $count = static fn (int $status): int => count(array_filter($items, static fn ($r): bool => $r->statusId === $status));

        return view('doctor.day', [
            'date' => $date,
            'reservations' => $items,
            'kpis' => [
                'pending' => $count(ReservationStatus::CONFIRMED),
                'attended' => $count(ReservationStatus::ATTENDED),
                'no_show' => $count(ReservationStatus::NO_SHOW),
            ],
        ]);
    }

    public function agenda(FilterRequest $request): View
    {
        $from = $request->validated('date_from') ?? LocalTime::today()->toDateString();
        $to = $request->validated('date_to') ?? LocalTime::today()->addDays(27)->toDateString();

        return view('doctor.agenda', [
            'from' => $from,
            'to' => $to,
            'agendas' => $this->schedules->ownAgenda($request->actor(), $from, $to),
        ]);
    }
}
