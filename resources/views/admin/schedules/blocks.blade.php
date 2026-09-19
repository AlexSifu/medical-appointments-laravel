@use('App\Support\LocalTime')
<x-app-layout title="Bloqueos" subtitle="Ausencias, reuniones y mantenimiento que impiden reservar.">
    <x-slot:actions>
        <x-button :href="route('admin.schedules.day')" variant="outline-primary" icon="bi-grid-3x3-gap">Vista del día</x-button>
    </x-slot:actions>

    <div class="row g-3">
        <div class="col-12 col-xl-8">
            @component('admin.schedules.partials.filters', ['route' => 'admin.schedules.blocks', 'filters' => $filters, 'doctors' => $doctors])
                <div class="col-6 col-md-2">
                    <label for="only_active" class="form-label">Mostrar</label>
                    <select id="only_active" name="only_active" class="form-select" data-autosubmit>
                        <option value="1" @selected((bool) ($filters['only_active'] ?? true))>Vigentes</option>
                        <option value="0" @selected(! (bool) ($filters['only_active'] ?? true))>Todos</option>
                    </select>
                </div>
            @endcomponent

            <x-card flush>
                @if ($page->isEmpty())
                    <x-empty-state icon="bi-check2-circle" title="No hay bloqueos con esos criterios" />
                @else
                    <x-table caption="Bloqueos" :headers="['Fecha y hora', 'Médico', 'Tipo', 'Motivo', 'Horarios', 'Estado', '>Acciones']">
                        @foreach ($page->items as $b)
                            <tr @class(['text-secondary' => ! $b->active])>
                                <td class="text-nowrap">{{ LocalTime::date($b->date) }}<div class="small">{{ LocalTime::time($b->start) }}–{{ LocalTime::time($b->end) }}</div></td>
                                <td>{{ $b->doctorName }}</td>
                                <td>{{ $blockTypes[$b->type] ?? $b->type }}</td>
                                <td class="small">{{ $b->reason }}<div class="text-secondary">{{ $b->createdBy }}</div></td>
                                <td>{{ $b->affectedSlots }}</td>
                                <td>
                                    @if ($b->active)
                                        <x-badge variant="warning">Vigente</x-badge>
                                    @else
                                        <x-badge variant="secondary">Levantado</x-badge>
                                        @if ($b->liftedBy)<div class="small">{{ $b->liftedBy }}</div>@endif
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if ($b->active)
                                        <form method="POST" action="{{ route('admin.schedules.unblock', $b->id) }}" data-confirm="¿Levantar este bloqueo? Los horarios volverán a estar disponibles.">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-success"><i class="bi bi-unlock" aria-hidden="true"></i> Levantar</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </x-table>
                @endif
            </x-card>
            <x-pagination :result="$page" />
        </div>

        <div class="col-12 col-xl-4">
            <x-card title="Nuevo bloqueo" icon="bi-slash-circle">
                <form method="POST" action="{{ route('admin.schedules.block') }}" data-submit-once>
                    @csrf
                    <x-select name="doctor_id" label="Médico" :options="collect($doctors)->pluck('name', 'id')->all()" placeholder="Selecciona" required id="block-doctor" />
                    <x-input name="date" label="Fecha" type="date" :min="now()->toDateString()" required id="block-date" />
                    <div class="row gx-2">
                        <div class="col-6"><x-input name="start" label="Desde" type="time" step="300" required id="block-start" /></div>
                        <div class="col-6"><x-input name="end" label="Hasta" type="time" step="300" required id="block-end" /></div>
                    </div>
                    <x-select name="type" label="Tipo" :options="array_diff_key($blockTypes, ['SLOT' => true])" required id="block-type" />
                    <x-input name="reason" label="Motivo" maxlength="250" required id="block-reason" />
                    <x-button variant="warning" icon="bi-slash-circle" class="w-100" loading="Bloqueando…">Bloquear</x-button>
                </form>
            </x-card>
        </div>
    </div>
</x-app-layout>
