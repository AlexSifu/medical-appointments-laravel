@use('App\Support\LocalTime')
<x-app-layout title="Hola, {{ auth()->user()->data->firstNames }}" subtitle="Gestiona tus citas médicas de forma rápida y segura.">
    <x-slot:actions>
        <x-button :href="route('booking.create')" icon="bi-calendar-plus">Reservar cita</x-button>
    </x-slot:actions>

    <div class="row g-3 mb-4">
        <div class="col-12 col-lg-7">
            @if ($data->next)
                @php $n = $data->next; @endphp
                <section class="card next-appointment h-100" aria-labelledby="next-title">
                    <div class="card-body p-4">
                        <p class="text-uppercase small fw-semibold text-muted-inv mb-2" id="next-title">Tu próxima cita</p>
                        <h2 class="h4 fw-bold mb-1">{{ $n->specialty }}</h2>
                        <p class="mb-3 text-muted-inv">{{ $n->doctorName }} · CMP {{ $n->cmp }}</p>
                        <div class="d-flex flex-wrap gap-4 mb-4">
                            <div><i class="bi bi-calendar-event me-2" aria-hidden="true"></i>{{ LocalTime::longDate($n->date) }}</div>
                            <div><i class="bi bi-clock me-2" aria-hidden="true"></i>{{ LocalTime::time($n->start) }} – {{ LocalTime::time($n->end) }}</div>
                            <div><i class="bi bi-geo-alt me-2" aria-hidden="true"></i>{{ $n->branch }}@if ($n->room) · Consultorio {{ $n->room }}@endif</div>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <a href="{{ route('reservations.show', $n->id) }}" class="btn btn-light btn-sm"><i class="bi bi-eye me-1" aria-hidden="true"></i>Ver detalle</a>
                            @can('reschedule', $n)
                                <a href="{{ route('reservations.reschedule.edit', $n->id) }}" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>Reprogramar</a>
                            @endcan
                        </div>
                    </div>
                </section>
            @else
                <x-card class="h-100">
                    <x-empty-state icon="bi-calendar-plus" title="No tienes citas próximas" message="Reserva una cita con el especialista que necesites.">
                        <x-button :href="route('booking.create')" icon="bi-calendar-plus">Reservar ahora</x-button>
                    </x-empty-state>
                </x-card>
            @endif
        </div>
        <div class="col-12 col-lg-5">
            <div class="row g-3 h-100">
                <div class="col-6 col-lg-12">
                    <x-stat-card label="Citas próximas" :value="$data->kpi('upcoming')" icon="bi-calendar-check" tone="blue" :href="route('patient.appointments')" />
                </div>
                <div class="col-6 col-lg-12">
                    <x-stat-card label="Médicos y especialidades" :value="count($data->specialties)" icon="bi-heart-pulse" tone="teal" :href="route('doctors.directory')" hint="especialidades disponibles" />
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-8">
            <x-card title="Próximas citas" icon="bi-calendar2-week" flush>
                <x-slot:actions>
                    <a href="{{ route('patient.appointments') }}" class="btn btn-sm btn-link">Ver todas</a>
                </x-slot:actions>
                @if ($data->reservations)
                    <ul class="list-group list-group-flush">
                        @foreach ($data->reservations as $r)
                            @include('partials.reservation-item', ['r' => $r])
                        @endforeach
                    </ul>
                @else
                    <x-empty-state icon="bi-calendar-x" title="Sin citas programadas" />
                @endif
            </x-card>

            <x-card title="Historial reciente" icon="bi-clock-history" flush class="mt-3">
                @if ($data->history)
                    <ul class="list-group list-group-flush">
                        @foreach ($data->history as $r)
                            @include('partials.reservation-item', ['r' => $r, 'actions' => false])
                        @endforeach
                    </ul>
                @else
                    <x-empty-state icon="bi-archive" title="Aún no tienes historial" />
                @endif
            </x-card>
        </div>
        <div class="col-12 col-xl-4">
            <x-card title="Reservar por especialidad" icon="bi-heart-pulse">
                <div class="d-flex flex-wrap gap-2">
                    @forelse ($data->specialties as $s)
                        <a href="{{ route('booking.create', ['specialty_id' => $s['EspecialidadId']]) }}" class="btn btn-sm btn-outline-primary">{{ $s['Nombre'] }}</a>
                    @empty
                        <p class="text-secondary mb-0">No hay especialidades activas.</p>
                    @endforelse
                </div>
            </x-card>
            <x-card title="Recuerda" icon="bi-info-circle" class="mt-3">
                <ul class="small text-secondary mb-0 ps-3">
                    <li>Llega 15 minutos antes de tu cita con tu documento de identidad.</li>
                    <li>Puedes cancelar o reprogramar con la anticipación mínima indicada en cada cita.</li>
                    <li>Mantén actualizados tus datos de contacto en <a href="{{ route('patient.profile') }}">Mi perfil</a>.</li>
                </ul>
            </x-card>
        </div>
    </div>

</x-app-layout>
