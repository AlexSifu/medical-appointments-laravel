{{-- Serie diaria (api.usp_DashboardSerieDiaria): gráfico + tabla equivalente accesible. --}}
@php
    $labels = array_map(fn ($r) => \App\Support\LocalTime::date((string) $r['Fecha'], 'd/m'), $data->series);
    $chart = [
        'type' => 'line',
        'labels' => $labels,
        'datasets' => [
            ['label' => 'Activas', 'data' => array_map(fn ($r) => (int) $r['Activas'], $data->series)],
            ['label' => 'Canceladas', 'data' => array_map(fn ($r) => (int) $r['Canceladas'], $data->series)],
            ['label' => 'No asistió', 'data' => array_map(fn ($r) => (int) $r['NoAsistio'], $data->series)],
        ],
    ];
@endphp
<x-card title="Reservas por día" icon="bi-graph-up" class="h-100">
    @if ($data->series)
        <div class="chart-box" style="height:260px">
            <canvas data-chart="{{ json_encode($chart) }}" role="img" aria-label="Gráfico de reservas por día: activas, canceladas y no asistió"></canvas>
        </div>
        <details class="mt-2">
            <summary class="small text-secondary">Ver datos en tabla</summary>
            <x-table caption="Reservas por día" :headers="['Fecha', '>Activas', '>Canceladas', '>No asistió']" class="table-sm mt-2">
                @foreach ($data->series as $i => $r)
                    <tr><td>{{ $labels[$i] }}</td><td class="text-end">{{ $r['Activas'] }}</td><td class="text-end">{{ $r['Canceladas'] }}</td><td class="text-end">{{ $r['NoAsistio'] }}</td></tr>
                @endforeach
            </x-table>
        </details>
    @else
        <x-empty-state icon="bi-graph-up" title="Sin datos para el periodo" />
    @endif
</x-card>
