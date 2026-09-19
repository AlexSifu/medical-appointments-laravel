<?php

namespace App\DTO;

/**
 * Datos del panel de inicio por rol. Cada rol usa solo las secciones que le corresponden.
 */
final readonly class DashboardData
{
    /**
     * @param  array<string, int|float|string|null>  $kpis
     * @param  list<array<string, mixed>>  $series  serie diaria (Fecha, Activas, Canceladas, NoAsistio)
     * @param  list<ReservationData>  $reservations  próximas citas / citas del día
     * @param  list<ReservationData>  $history
     * @param  list<array<string, mixed>>  $specialties  resumen por especialidad
     */
    public function __construct(
        public string $role,
        public array $kpis = [],
        public array $series = [],
        public array $reservations = [],
        public array $history = [],
        public array $specialties = [],
        public ?ReservationData $next = null,
    ) {}

    public function kpi(string $key, int|float $default = 0): int|float
    {
        $value = $this->kpis[$key] ?? $default;

        return is_numeric($value) ? $value + 0 : $default;
    }
}
