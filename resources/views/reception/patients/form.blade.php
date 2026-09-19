@php
    $editing = $patient !== null;
    $documentTypes = ['DNI' => 'DNI', 'CE' => 'Carné de extranjería', 'PASAPORTE' => 'Pasaporte'];
    $sexes = ['F' => 'Femenino', 'M' => 'Masculino', 'X' => 'No especifica'];
@endphp
<x-app-layout :title="$editing ? 'Editar paciente' : 'Registrar paciente'" :subtitle="$editing ? $patient->fullName : 'Los campos con * son obligatorios.'">
    <x-slot:breadcrumb>
        <a href="{{ $editing ? route('reception.patients.show', $patient->id) : route('reception.patients.index') }}" class="small text-decoration-none"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Volver</a>
    </x-slot:breadcrumb>

    <form method="POST" action="{{ $editing ? route('reception.patients.update', $patient->id) : route('reception.patients.store') }}" data-submit-once novalidate>
        @csrf
        @if ($editing)
            @method('PUT')
            <input type="hidden" name="version" value="{{ $patient->version }}">
        @endif

        <x-card title="Identificación" icon="bi-person-vcard" class="mb-3">
            <div class="row gx-3">
                <div class="col-12 col-md-4"><x-select name="document_type" label="Tipo de documento" :options="$documentTypes" :value="$patient?->documentType ?? 'DNI'" required /></div>
                <div class="col-12 col-md-4"><x-input name="document_number" label="Número de documento" :value="$patient?->documentNumber" maxlength="20" required autocomplete="off" help="Solo letras y números." /></div>
                <div class="col-12 col-md-4"><x-input name="birth_date" label="Fecha de nacimiento" type="date" :value="$patient?->birthDate ? substr($patient->birthDate, 0, 10) : null" :max="now()->toDateString()" required /></div>
                <div class="col-12 col-md-5"><x-input name="first_names" label="Nombres" :value="$patient?->firstNames" maxlength="100" required autocomplete="given-name" /></div>
                <div class="col-12 col-md-5"><x-input name="last_names" label="Apellidos" :value="$patient?->lastNames" maxlength="100" required autocomplete="family-name" /></div>
                <div class="col-12 col-md-2"><x-select name="sex" label="Sexo" :options="$sexes" :value="$patient?->sex" placeholder="—" required /></div>
            </div>
        </x-card>

        <x-card title="Contacto" icon="bi-telephone" class="mb-3">
            <div class="row gx-3">
                <div class="col-12 col-md-6"><x-input name="phone" label="Teléfono" type="tel" :value="$patient?->phone" maxlength="20" /></div>
                <div class="col-12 col-md-6"><x-input name="email" label="Correo electrónico" type="email" :value="$patient?->email" maxlength="150" /></div>
                <div class="col-12 col-md-6"><x-input name="address" label="Dirección" :value="$patient?->address" maxlength="200" /></div>
                <div class="col-12 col-md-6"><x-input name="emergency_contact" label="Contacto de emergencia" :value="$patient?->emergencyContact" maxlength="150" /></div>
            </div>
            @if ($editing)
                <div class="form-check form-switch">
                    <input type="hidden" name="active" value="0">
                    <input class="form-check-input" type="checkbox" role="switch" id="active" name="active" value="1" @checked(old('active', $patient->active))>
                    <label class="form-check-label" for="active">Paciente activo</label>
                </div>
            @endif
        </x-card>

        <div class="d-flex gap-2">
            <x-button icon="bi-check2" loading="Guardando…">{{ $editing ? 'Guardar cambios' : 'Registrar paciente' }}</x-button>
            <x-button :href="$editing ? route('reception.patients.show', $patient->id) : route('reception.patients.index')" variant="outline-secondary">Cancelar</x-button>
        </div>
    </form>
</x-app-layout>
