{{-- KPIs de api.usp_DashboardResumen (Recepción / Administración / Auditoría). --}}
@php
    $total = (int) $data->kpi('SlotsTotalesHoy');
    $free = (int) $data->kpi('SlotsLibresHoy');
    $occupancy = $total > 0 ? round(($total - $free) * 100 / $total) : 0;
@endphp
<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3"><x-stat-card label="Citas de hoy" :value="$data->kpi('CitasHoy')" icon="bi-calendar-day" tone="blue" :hint="$data->kpi('PendientesHoy').' pendientes'" /></div>
    <div class="col-6 col-xl-3"><x-stat-card label="Atendidas hoy" :value="$data->kpi('AtendidasHoy')" icon="bi-check2-circle" tone="success" :hint="$data->kpi('NoAsistioHoy').' no asistieron'" /></div>
    <div class="col-6 col-xl-3"><x-stat-card label="Próximos 7 días" :value="$data->kpi('ProximosSieteDias')" icon="bi-calendar-week" tone="teal" /></div>
    <div class="col-6 col-xl-3"><x-stat-card label="Ocupación de hoy" :value="$occupancy.'%'" icon="bi-speedometer2" tone="cyan" :hint="$free.' de '.$total.' horarios libres'" /></div>
    @if ($extended ?? false)
        <div class="col-6 col-xl-3"><x-stat-card label="Creadas este mes" :value="$data->kpi('CreadasMes')" icon="bi-plus-circle" tone="navy" /></div>
        <div class="col-6 col-xl-3"><x-stat-card label="Canceladas este mes" :value="$data->kpi('CanceladasMes')" icon="bi-x-circle" tone="danger" /></div>
        <div class="col-6 col-xl-3"><x-stat-card label="Pacientes activos" :value="number_format($data->kpi('PacientesActivos'))" icon="bi-people" tone="blue" /></div>
        <div class="col-6 col-xl-3"><x-stat-card label="Médicos activos" :value="$data->kpi('MedicosActivos')" icon="bi-person-badge" tone="teal" /></div>
    @endif
</div>
