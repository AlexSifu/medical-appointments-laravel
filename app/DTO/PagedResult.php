<?php

namespace App\DTO;

use App\Support\Row;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Request;

/**
 * Página devuelta por un SP paginado (OFFSET/FETCH + COUNT(*) OVER() AS TotalFilas).
 *
 * @template T
 */
final readonly class PagedResult
{
    /** @param list<T> $items */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
    ) {}

    /**
     * @template R
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  callable(array<string, mixed>): R  $map
     * @return self<R>
     */
    public static function fromRows(array $rows, int $page, int $perPage, callable $map): self
    {
        $total = $rows === [] ? 0 : Row::int($rows[0], 'TotalFilas', count($rows));

        return new self(array_map($map, $rows), $total, $page, $perPage);
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    /** Paginador de Laravel para la vista (no vuelve a paginar: la página ya viene de SQL). */
    public function paginator(): LengthAwarePaginator
    {
        return new LengthAwarePaginator($this->items, $this->total, $this->perPage, $this->page, [
            'path' => Request::url(),
            'query' => Request::query(),
        ]);
    }
}
