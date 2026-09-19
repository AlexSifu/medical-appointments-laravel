@use('App\Support\LocalTime')
<x-app-layout title="Bitácora" subtitle="Registro de acciones del sistema (solo lectura). Nunca contiene contraseñas ni tokens.">
    <x-card class="mb-3">
        <form method="GET" action="{{ route('audit.index') }}" class="row g-2 align-items-end" aria-label="Filtros de bitácora">
            <div class="col-12 col-md-4 col-xl-3">
                <label for="q" class="form-label">Texto</label>
                <input type="search" id="q" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" maxlength="100" placeholder="Usuario, detalle o IP">
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <label for="action" class="form-label">Acción</label>
                <select id="action" name="action" class="form-select">
                    <option value="">Todas</option>
                    @foreach ($actions as $a)
                        <option value="{{ $a }}" @selected(($filters['action'] ?? '') === $a)>{{ $a }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <label for="entity" class="form-label">Entidad</label>
                <select id="entity" name="entity" class="form-select">
                    <option value="">Todas</option>
                    @foreach ($entities as $e)
                        <option value="{{ $e }}" @selected(($filters['entity'] ?? '') === $e)>{{ $e }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3 col-xl-1">
                <label for="result" class="form-label">Resultado</label>
                <select id="result" name="result" class="form-select">
                    <option value="">Todos</option>
                    <option value="ok" @selected(($filters['result'] ?? '') === 'ok')>Éxito</option>
                    <option value="error" @selected(($filters['result'] ?? '') === 'error')>Error</option>
                </select>
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <label for="date_from" class="form-label">Desde</label>
                <input type="date" id="date_from" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-control">
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <label for="date_to" class="form-label">Hasta</label>
                <input type="date" id="date_to" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-control">
            </div>
            <div class="col-6 col-md-6 col-xl-3">
                <label for="correlation_id" class="form-label">Correlation id</label>
                <input type="text" id="correlation_id" name="correlation_id" value="{{ $filters['correlation_id'] ?? '' }}" class="form-control" maxlength="64" pattern="[A-Za-z0-9-]+">
            </div>
            <div class="col-12 col-md-3 col-xl-2 d-flex gap-2">
                <button class="btn btn-primary flex-grow-1"><i class="bi bi-search me-1" aria-hidden="true"></i>Buscar</button>
                <a href="{{ route('audit.index') }}" class="btn btn-outline-secondary" aria-label="Limpiar filtros"><i class="bi bi-x-lg" aria-hidden="true"></i></a>
            </div>
            @if (! empty($filters['user_id']))
                <input type="hidden" name="user_id" value="{{ $filters['user_id'] }}">
            @endif
        </form>
    </x-card>

    <x-card flush>
        @if ($page->isEmpty())
            <x-empty-state icon="bi-journal-x" title="Sin registros para esos filtros" />
        @else
            <x-table caption="Bitácora" :headers="['Fecha', 'Usuario', 'Acción', 'Entidad', 'Resultado', 'Detalle']">
                @foreach ($page->items as $e)
                    <tr>
                        <td class="small text-nowrap">{{ LocalTime::fromUtc($e->dateUtc, 'd/m/Y H:i:s') }}</td>
                        <td>
                            @if ($e->userId)
                                <a href="{{ route('audit.index', ['user_id' => $e->userId]) }}" class="text-decoration-none" title="Filtrar por este usuario">{{ $e->username ?? '#'.$e->userId }}</a>
                            @else
                                <span class="text-secondary">{{ $e->username ?? 'Anónimo' }}</span>
                            @endif
                            @if ($e->ip)<div class="small text-secondary">{{ $e->ip }}</div>@endif
                        </td>
                        <td><span class="code-pill">{{ $e->action }}</span></td>
                        <td class="small">{{ $e->entity ?? '—' }}@if ($e->entityId) #{{ $e->entityId }}@endif</td>
                        <td>
                            @if ($e->success)
                                <x-badge variant="success" icon="bi-check2">Éxito</x-badge>
                            @else
                                <x-badge variant="danger" icon="bi-x">Error</x-badge>
                            @endif
                        </td>
                        <td class="small" style="max-width: 28rem">
                            @if ($e->detail)
                                <details>
                                    <summary class="text-truncate">{{ \Illuminate\Support\Str::limit($e->detail, 60) }}</summary>
                                    <pre class="small text-pre-wrap mb-1 mt-2">{{ $e->detail }}</pre>
                                    @if ($e->correlationId)
                                        <a href="{{ route('audit.index', ['correlation_id' => $e->correlationId]) }}" class="small">Correlation {{ $e->correlationId }}</a>
                                    @endif
                                </details>
                            @elseif ($e->correlationId)
                                <a href="{{ route('audit.index', ['correlation_id' => $e->correlationId]) }}" class="small text-secondary">{{ $e->correlationId }}</a>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>
    <x-pagination :result="$page" />
</x-app-layout>
