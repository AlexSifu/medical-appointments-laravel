<?php

namespace App\Models;

/** Estados de reserva (clin.EstadosReserva). Las transiciones válidas las controla SQL Server. */
final class ReservationStatus
{
    public const TABLE = 'clin.EstadosReserva';

    public const CONFIRMED = 1;

    public const CANCELLED = 2;

    public const RESCHEDULED = 3;

    public const ATTENDED = 4;

    public const NO_SHOW = 5;

    public const CODES = [
        self::CONFIRMED => 'CONFIRMADA',
        self::CANCELLED => 'CANCELADA',
        self::RESCHEDULED => 'REPROGRAMADA',
        self::ATTENDED => 'ATENDIDA',
        self::NO_SHOW => 'NO_ASISTIO',
    ];

    public const LABELS = [
        self::CONFIRMED => 'Confirmada',
        self::CANCELLED => 'Cancelada',
        self::RESCHEDULED => 'Reprogramada',
        self::ATTENDED => 'Atendida',
        self::NO_SHOW => 'No asistió',
    ];

    /** Variante de color para el badge de estado. */
    public const VARIANTS = [
        'CONFIRMADA' => 'primary',
        'CANCELADA' => 'danger',
        'REPROGRAMADA' => 'warning',
        'ATENDIDA' => 'success',
        'NO_ASISTIO' => 'secondary',
    ];

    public const ICONS = [
        'CONFIRMADA' => 'bi-calendar-check',
        'CANCELADA' => 'bi-x-circle',
        'REPROGRAMADA' => 'bi-arrow-repeat',
        'ATENDIDA' => 'bi-check2-circle',
        'NO_ASISTIO' => 'bi-person-x',
    ];

    public static function variant(string $code): string
    {
        return self::VARIANTS[$code] ?? 'secondary';
    }

    public static function icon(string $code): string
    {
        return self::ICONS[$code] ?? 'bi-circle';
    }

    public static function label(string $code): string
    {
        $id = array_search($code, self::CODES, true);

        return $id === false ? $code : self::LABELS[$id];
    }
}
