<x-app-layout title="Panel de auditoría" subtitle="Vista de solo lectura sobre la operación y la trazabilidad.">
    <x-slot:actions>
        <x-button :href="route('audit.index')" icon="bi-shield-check">Bitácora</x-button>
        @can('reportes.ver')
            <x-button :href="route('reports.index')" variant="outline-primary" icon="bi-bar-chart-line">Reportes</x-button>
        @endcan
    </x-slot:actions>

    @include('dashboard.partials.kpis', ['extended' => true])

    <div class="row g-3">
        <div class="col-12 col-xl-6">
            @include('dashboard.partials.series')
        </div>
        <div class="col-12 col-xl-6">
            <x-card title="Actividad reciente" icon="bi-clock-history" flush class="h-100">
                @if ($data->history)
                    <ul class="list-group list-group-flush">
                        @foreach ($data->history as $e)
                            <li class="list-group-item d-flex gap-2">
                                <i @class(['bi mt-1', 'bi-check-circle text-success' => $e->success, 'bi-exclamation-octagon text-danger' => ! $e->success]) aria-hidden="true"></i>
                                <div class="min-w-0">
                                    <div class="fw-semibold">{{ $e->action }} <span class="text-secondary fw-normal">· {{ $e->entity }} {{ $e->entityId }}</span></div>
                                    <div class="small text-secondary">{{ $e->username ?? 'sistema' }} · {{ \App\Support\LocalTime::fromUtc($e->dateUtc) }} · {{ $e->success ? 'Éxito' : 'Error' }}</div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <x-empty-state icon="bi-shield" title="Sin actividad registrada" />
                @endif
            </x-card>
        </div>
    </div>
</x-app-layout>
