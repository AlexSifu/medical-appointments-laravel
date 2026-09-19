<?php

namespace App\Repositories\Contracts;

interface ReportRepositoryInterface
{
    /** @return array<string, int> */
    public function dashboardSummary(int $actorId): array;

    /** @return list<array<string, mixed>> Fecha, Activas, Canceladas, NoAsistio */
    public function dailySeries(int $actorId): array;

    /** @return list<array<string, mixed>> */
    public function reservationsBySpecialty(int $actorId, string $from, string $to, ?int $branchId): array;

    /** @return list<array<string, mixed>> */
    public function doctorOccupancy(int $actorId, string $from, string $to, ?int $specialtyId, ?int $branchId): array;

    /** @return list<array<string, mixed>> */
    public function cancellations(int $actorId, string $from, string $to, ?int $specialtyId): array;

    /** @return list<array<string, mixed>> */
    public function noShows(int $actorId, string $from, string $to, ?int $branchId): array;
}
