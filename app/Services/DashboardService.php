<?php

namespace App\Services;

use App\DTO\DashboardData;
use App\DTO\ReservationData;
use App\Models\Permission;
use App\Models\ReservationStatus;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Contracts\AuditRepositoryInterface;
use App\Repositories\Contracts\CatalogRepositoryInterface;
use App\Repositories\Contracts\ReportRepositoryInterface;
use App\Repositories\Contracts\ReservationRepositoryInterface;
use App\Support\LocalTime;

/** Arma el panel de inicio de cada rol (§72) a partir de SP de consulta. */
final class DashboardService
{
    public function __construct(
        private readonly ReservationRepositoryInterface $reservations,
        private readonly ReportRepositoryInterface $reports,
        private readonly CatalogRepositoryInterface $catalogs,
        private readonly AuditRepositoryInterface $audit,
    ) {}

    /** Vista de panel que corresponde al rol principal del usuario. */
    public function viewFor(User $user): string
    {
        return match (true) {
            $user->isPatient() => 'patient',
            $user->isDoctor() && ! $user->can(Permission::RESERVATIONS_MANAGE) => 'doctor',
            $user->hasRole(Role::SUPERADMIN), $user->hasRole(Role::ADMIN) => 'admin',
            $user->can(Permission::RESERVATIONS_MANAGE) => 'reception',
            $user->can(Permission::AUDIT_VIEW) => 'auditor',
            default => 'basic',
        };
    }

    public function build(User $user): DashboardData
    {
        return match ($view = $this->viewFor($user)) {
            'patient' => $this->patient($user),
            'doctor' => $this->doctor($user),
            'admin' => $this->admin($user),
            'reception' => $this->reception($user),
            'auditor' => $this->auditor($user),
            default => new DashboardData($view),
        };
    }

    private function patient(User $user): DashboardData
    {
        $upcoming = $this->reservations->mine($user->id(), 'PROXIMAS', null, 1, 5)->items;
        $history = $this->reservations->mine($user->id(), 'HISTORIAL', null, 1, 5)->items;

        return new DashboardData(
            role: 'patient',
            kpis: ['upcoming' => count($upcoming)],
            reservations: $upcoming,
            history: $history,
            specialties: $this->catalogs->specialties(),
            next: $upcoming[0] ?? null,
        );
    }

    private function doctor(User $user): DashboardData
    {
        $today = $this->reservations->doctorDay($user->id(), LocalTime::today()->toDateString());
        $count = static fn (int $status): int => count(array_filter($today, static fn (ReservationData $r): bool => $r->statusId === $status));

        return new DashboardData(
            role: 'doctor',
            kpis: [
                'today' => count(array_filter($today, static fn (ReservationData $r): bool => $r->statusId !== ReservationStatus::CANCELLED && $r->statusId !== ReservationStatus::RESCHEDULED)),
                'pending' => $count(ReservationStatus::CONFIRMED),
                'attended' => $count(ReservationStatus::ATTENDED),
                'no_show' => $count(ReservationStatus::NO_SHOW),
            ],
            reservations: $today,
        );
    }

    private function reception(User $user): DashboardData
    {
        $today = LocalTime::today()->toDateString();
        $todays = $this->reservations->search($user->id(), ['FechaDesde' => $today, 'FechaHasta' => $today], 1, 50)->items;
        $cancelled = $this->reservations->search($user->id(), [
            'FechaDesde' => $today,
            'EstadoReservaId' => ReservationStatus::CANCELLED,
        ], 1, 5)->items;

        return new DashboardData(
            role: 'reception',
            kpis: $this->reports->dashboardSummary($user->id()),
            reservations: $todays,
            history: $cancelled,
        );
    }

    private function admin(User $user): DashboardData
    {
        $to = LocalTime::today();

        return new DashboardData(
            role: 'admin',
            kpis: $this->reports->dashboardSummary($user->id()),
            series: $this->reports->dailySeries($user->id()),
            specialties: $user->can(Permission::REPORTS_VIEW)
                ? $this->reports->reservationsBySpecialty($user->id(), $to->subDays(30)->toDateString(), $to->addDays(30)->toDateString(), null)
                : [],
        );
    }

    private function auditor(User $user): DashboardData
    {
        return new DashboardData(
            role: 'auditor',
            kpis: $this->reports->dashboardSummary($user->id()),
            series: $this->reports->dailySeries($user->id()),
            history: $this->audit->search($user->id(), [], 1, 8)->items,
        );
    }
}
