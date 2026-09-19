<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Fechas para la interfaz. Las fechas/horas de cita (FechaCita, HoraInicio) ya están en hora
 * local de la clínica (clin.fn_AhoraLocal, America/Lima); las columnas *Utc se convierten aquí.
 */
final class LocalTime
{
    public static function zone(): string
    {
        return (string) config('app.display_timezone', 'America/Lima');
    }

    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(self::zone());
    }

    public static function today(): CarbonImmutable
    {
        return self::now()->startOfDay();
    }

    /** "lun. 21 sep. 2026" */
    public static function date(?string $date, string $format = 'D j M Y'): string
    {
        if ($date === null || $date === '') {
            return '—';
        }

        try {
            return CarbonImmutable::parse(substr($date, 0, 10), self::zone())->locale('es')->translatedFormat($format);
        } catch (Throwable) {
            return $date;
        }
    }

    /** "lunes, 21 de septiembre de 2026" */
    public static function longDate(?string $date): string
    {
        return self::date($date, 'l, j \d\e F \d\e Y');
    }

    /** Hora 'HH:MM:SS' o 'HH:MM' → 'HH:MM'. */
    public static function time(?string $time): string
    {
        return $time === null || $time === '' ? '—' : substr($time, 0, 5);
    }

    /** DATETIME2 en UTC → hora local legible. */
    public static function fromUtc(?string $utc, string $format = 'd/m/Y H:i'): string
    {
        if ($utc === null || $utc === '') {
            return '—';
        }

        try {
            return CarbonImmutable::parse($utc, 'UTC')->setTimezone(self::zone())->locale('es')->translatedFormat($format);
        } catch (Throwable) {
            return $utc;
        }
    }

    public static function isToday(?string $date): bool
    {
        return $date !== null && substr($date, 0, 10) === self::today()->toDateString();
    }

    /** Valida 'Y-m-d' estricto. */
    public static function isDate(?string $value): bool
    {
        if ($value === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return false;
        }
        [$y, $m, $d] = array_map('intval', explode('-', $value));

        return checkdate($m, $d, $y);
    }
}
