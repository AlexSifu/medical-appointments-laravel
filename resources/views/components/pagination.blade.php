@props(['result'])
{{-- $result: App\DTO\PagedResult (paginado en SQL Server con OFFSET/FETCH). --}}
@if ($result->total > 0)
    <nav {{ $attributes->class(['d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mt-3']) }} aria-label="Paginación">
        <p class="small text-secondary mb-0">
            Mostrando {{ ($result->page - 1) * $result->perPage + 1 }}–{{ min($result->page * $result->perPage, $result->total) }}
            de {{ number_format($result->total) }} registros
        </p>
        @if ($result->total > $result->perPage)
            {{ $result->paginator()->onEachSide(1)->links() }}
        @endif
    </nav>
@endif
