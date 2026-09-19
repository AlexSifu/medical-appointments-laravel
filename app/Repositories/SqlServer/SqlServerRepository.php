<?php

namespace App\Repositories\SqlServer;

use App\DTO\PagedResult;
use App\Support\Database\StoredProcedureExecutor;

/**
 * Base de los repositorios SQL Server. Los nombres de SP/vistas son constantes de cada
 * repositorio; los valores del request solo viajan como parámetros enlazados.
 */
abstract class SqlServerRepository
{
    public const MAX_PAGE_SIZE = 100;

    public function __construct(protected readonly StoredProcedureExecutor $sql) {}

    /**
     * @template R
     *
     * @param  callable(array<string, mixed>): R  $map
     * @return PagedResult<R>
     */
    protected function paged(string $procedure, array $params, int $page, int $perPage, callable $map): PagedResult
    {
        $page = max(1, $page);
        $perPage = min(self::MAX_PAGE_SIZE, max(1, $perPage));
        $rows = $this->sql->select($procedure, $params + ['Pagina' => $page, 'TamanoPagina' => $perPage]);

        return PagedResult::fromRows($rows, $page, $perPage, $map);
    }

    /** Texto de búsqueda normalizado: vacío → null. */
    protected static function text(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
