<?php

namespace App\Support;

/**
 * Lectura tipada de filas devueltas por procedimientos almacenados (arrays asociativos).
 * Evita propagar arrays/stdClass sin tipo por el resto de la aplicación.
 */
final class Row
{
    public static function int(array $row, string $key, int $default = 0): int
    {
        return isset($row[$key]) ? (int) $row[$key] : $default;
    }

    public static function intOrNull(array $row, string $key): ?int
    {
        return isset($row[$key]) && $row[$key] !== '' ? (int) $row[$key] : null;
    }

    public static function str(array $row, string $key, string $default = ''): string
    {
        return isset($row[$key]) ? (string) $row[$key] : $default;
    }

    public static function strOrNull(array $row, string $key): ?string
    {
        return isset($row[$key]) && $row[$key] !== '' ? (string) $row[$key] : null;
    }

    public static function bool(array $row, string $key, bool $default = false): bool
    {
        return isset($row[$key]) ? (bool) (int) $row[$key] : $default;
    }

    public static function float(array $row, string $key, float $default = 0.0): float
    {
        return isset($row[$key]) ? (float) $row[$key] : $default;
    }

    /** Hora 'HH:MM:SS' → 'HH:MM'. */
    public static function time(array $row, string $key): string
    {
        return substr(self::str($row, $key), 0, 5);
    }

    /**
     * Lista separada por comas (STRING_AGG) → array.
     *
     * @return list<string>
     */
    public static function list(array $row, string $key, string $separator = ','): array
    {
        $value = self::strOrNull($row, $key);

        return $value === null ? [] : array_values(array_filter(array_map('trim', explode($separator, $value)), fn ($v) => $v !== ''));
    }

    /** @return list<int> */
    public static function intList(array $row, string $key): array
    {
        return array_map('intval', self::list($row, $key));
    }
}
