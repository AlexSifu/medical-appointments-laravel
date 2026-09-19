{{-- Tabla de reservas para personal (Recepción / Administración). $items: list<ReservationData>; $showDate: bool --}}
@use('App\Support\LocalTime')
<x-table caption="Reservas" :headers="array_values(array_filter([($showDate ?? true) ? 'Fecha' : null, 'Hora', 'Código', 'Paciente', 'Médico / Especialidad', 'Sede', 'Estado', '>Acciones']))">
    @foreach ($items as $r)
        <tr>
            @if ($showDate ?? true)
                <td class="text-nowrap">{{ LocalTime::date($r->date) }}</td>
            @endif
            <td class="fw-semibold text-nowrap">{{ LocalTime::time($r->start) }}</td>
            <td><span class="code-pill">{{ $r->code }}</span></td>
            <td>
                @if ($r->patientId && auth()->user()->can('pacientes.ver'))
                    <a href="{{ route('reception.patients.show', $r->patientId) }}" class="text-decoration-none">{{ $r->patientName }}</a>
                @else
                    {{ $r->patientName }}
                @endif
                <div class="small text-secondary">{{ $r->patientDocument }}</div>
            </td>
            <td>{{ $r->doctorName }}<div class="small text-secondary">{{ $r->specialty }}</div></td>
            <td>{{ $r->branch }}@if ($r->room)<div class="small text-secondary">Cons. {{ $r->room }}</div>@endif</td>
            <td><x-reservation-status :code="$r->statusCode" :name="$r->statusName" /></td>
            <td class="text-end">
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Acciones<span class="visually-hidden"> para {{ $r->code }}</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="{{ route('reservations.show', $r->id) }}"><i class="bi bi-eye me-2" aria-hidden="true"></i>Ver detalle</a></li>
                        @can('reschedule', $r)
                            <li><a class="dropdown-item" href="{{ route('reservations.reschedule.edit', $r->id) }}"><i class="bi bi-arrow-repeat me-2" aria-hidden="true"></i>Reprogramar</a></li>
                        @endcan
                        @can('cancel', $r)
                            <li>
                                <button type="button" class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#cancelModal"
                                    data-action="{{ route('reservations.cancel', $r->id) }}" data-version="{{ $r->version }}" data-code="{{ $r->code }}">
                                    <i class="bi bi-x-circle me-2" aria-hidden="true"></i>Cancelar
                                </button>
                            </li>
                        @endcan
                        @can('close', $r)
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="{{ route('reservations.attended', $r->id) }}" data-confirm="¿Marcar la cita {{ $r->code }} como atendida?">
                                    @csrf <input type="hidden" name="version" value="{{ $r->version }}">
                                    <button class="dropdown-item"><i class="bi bi-check2-circle me-2 text-success" aria-hidden="true"></i>Atendida</button>
                                </form>
                            </li>
                            <li>
                                <form method="POST" action="{{ route('reservations.no-show', $r->id) }}" data-confirm="¿Registrar que el paciente no asistió a la cita {{ $r->code }}?" data-confirm-variant="danger">
                                    @csrf <input type="hidden" name="version" value="{{ $r->version }}">
                                    <button class="dropdown-item"><i class="bi bi-person-x me-2 text-danger" aria-hidden="true"></i>No asistió</button>
                                </form>
                            </li>
                        @endcan
                    </ul>
                </div>
            </td>
        </tr>
    @endforeach
</x-table>
