@use('App\Services\CatalogService')
@use('App\Support\LocalTime')
@php
    $pctColumns = ['OcupacionPct', 'TasaNoAsistenciaPct'];
    $decimalColumns = ['HorasAnticipacionPromedio'];
    $chartConfig = [
        'type' => 'bar',
        'horizontal' => count($chart['labels']) > 6,
        'labels' => $chart['labels'],
        'datasets' => [['label' => $chart['label'], 'data' => $chart['values']]],
    ];
    $numericKeys = array_values(array_filter(array_keys($columns), fn ($c) => ! in_array($c, ['Especialidad', 'MedicoNombre', 'CMP', 'Motivo'], true)));
@endphp
<x-app-layout title="Reportes" :subtitle="LocalTime::date($result['from'], 'd/m/Y').' al '.LocalTime::date($result['to'], 'd/m/Y')">
    <x-slot:actions>
        <x-button :href="request()->fullUrlWithQuery(['export' => 'csv'])" variant="outline-primary" icon="bi-filetype-csv">Exportar CSV</x-button>
        <x-button type="button" variant="outline-secondary" icon="bi-printer" data-print>Imprimir</x-button>
    </x-slot:actions>

    <nav aria-label="Reportes" class="mb-3">
        <ul class="nav nav-pills flex-wrap gap-1">
            @foreach ($reports as $key => $label)
                <li class="nav-item">
                    <a @class(['nav-link', 'active' => $key === $report]) href="{{ route('reports.index', ['report' => $key] + array_intersect_key($filters, array_flip(['date_from', 'date_to']))) }}"
                        @if ($key === $report) aria-current="page" @endif>{{ $label }}</a>
                </li>
            @endforeach
        </ul>
    </nav>

    <x-card class="mb-3 d-print-none">
        <form method="GET" action="{{ route('reports.index', $report) }}" class="row g-2 align-items-end" aria-label="Filtros del reporte">
            <div class="col-6 col-md-3">
                <label for="date_from" class="form-label">Desde</label>
                <input type="date" id="date_from" name="date_from" value="{{ $result['from'] }}" class="form-control">
            </div>
            <div class="col-6 col-md-3">
                <label for="date_to" class="form-label">Hasta</label>
                <input type="date" id="date_to" name="date_to" value="{{ $result['to'] }}" class="form-control">
            </div>
            @if (in_array($report, ['ocupacion', 'cancelaciones'], true))
                <div class="col-6 col-md-2">
                    <label for="specialty_id" class="form-label">Especialidad</label>
                    <select id="specialty_id" name="specialty_id" class="form-select">
                        <option value="">Todas</option>
                        @foreach (CatalogService::options($specialties, 'EspecialidadId') as $id => $name)
                            <option value="{{ $id }}" @selected((string) ($filters['specialty_id'] ?? '') === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            @if ($report !== 'cancelaciones')
                <div class="col-6 col-md-2">
                    <label for="branch_id" class="form-label">Sede</label>
                    <select id="branch_id" name="branch_id" class="form-select">
                        <option value="">Todas</option>
                        @foreach (CatalogService::options($branches, 'SedeId') as $id => $name)
                            <option value="{{ $id }}" @selected((string) ($filters['branch_id'] ?? '') === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="col-12 col-md-2">
                <button class="btn btn-primary w-100"><i class="bi bi-bar-chart me-1" aria-hidden="true"></i>Generar</button>
            </div>
        </form>
    </x-card>

    @if (count($result['rows']) === 0)
        <x-empty-state icon="bi-bar-chart" title="Sin datos en el periodo" message="Amplía el rango de fechas o quita filtros." />
    @else
        <div class="row g-3">
            <div class="col-12 col-xl-5">
                <x-card :title="$chart['label']" icon="bi-bar-chart">
                    <div class="chart-box">
                        <canvas data-chart='@json($chartConfig)' role="img" aria-label="Gráfico: {{ $chart['label'] }} por {{ strtolower(reset($columns)) }}. Los datos están en la tabla."></canvas>
                    </div>
                </x-card>
            </div>
            <div class="col-12 col-xl-7">
                <x-card :title="$reports[$report]" icon="bi-table" flush>
                    <x-table :caption="$reports[$report]" :headers="array_map(fn ($c, $h) => in_array($c, $numericKeys, true) ? '>'.$h : $h, array_keys($columns), array_values($columns))">
                        @foreach ($result['rows'] as $row)
                            <tr>
                                @foreach ($columns as $col => $header)
                                    @php $v = $row[$col] ?? null; @endphp
                                    <td @class(['text-end' => in_array($col, $numericKeys, true)])>
                                        @if ($v === null)
                                            —
                                        @elseif (in_array($col, $pctColumns, true))
                                            {{ number_format((float) $v, 1) }} %
                                        @elseif (in_array($col, $decimalColumns, true))
                                            {{ number_format((float) $v, 1) }}
                                        @else
                                            {{ $v }}
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </x-table>
                </x-card>
            </div>
        </div>
    @endif
</x-app-layout>
