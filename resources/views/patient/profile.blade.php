@use('App\Support\LocalTime')
<x-app-layout title="Mi perfil" subtitle="Actualiza tus datos de contacto.">
    <div class="row g-3">
        <div class="col-12 col-lg-4">
            <x-card>
                <div class="text-center">
                    <span class="avatar avatar-lg mx-auto mb-2" aria-hidden="true">{{ mb_substr($patient->firstNames, 0, 1).mb_substr($patient->lastNames, 0, 1) }}</span>
                    <h2 class="h5 mb-0">{{ $patient->fullName }}</h2>
                    <p class="text-secondary small mb-3">{{ $patient->document() }}</p>
                </div>
                <dl class="detail-list small mb-0">
                    <dt>Fecha de nacimiento</dt><dd>{{ LocalTime::date($patient->birthDate, 'j \d\e F \d\e Y') }}</dd>
                    <dt>Usuario</dt><dd>{{ $patient->username ?? auth()->user()->data->username }}</dd>
                    <dt>Citas confirmadas</dt><dd class="mb-0">{{ $patient->confirmedReservations ?? 0 }}</dd>
                </dl>
                <p class="small text-secondary mt-3 mb-0"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>Para corregir nombre, documento o fecha de nacimiento acércate a recepción.</p>
            </x-card>
        </div>
        <div class="col-12 col-lg-8">
            <x-card title="Datos de contacto" icon="bi-telephone">
                <form method="POST" action="{{ route('patient.profile.update') }}" data-submit-once novalidate>
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="version" value="{{ $patient->version }}">
                    <div class="row gx-3">
                        <div class="col-12 col-md-6"><x-input name="phone" label="Teléfono" type="tel" :value="$patient->phone" maxlength="20" autocomplete="tel" /></div>
                        <div class="col-12 col-md-6"><x-input name="email" label="Correo electrónico" type="email" :value="$patient->email" maxlength="150" autocomplete="email" /></div>
                        <div class="col-12"><x-input name="address" label="Dirección" :value="$patient->address" maxlength="200" autocomplete="street-address" /></div>
                        <div class="col-12"><x-input name="emergency_contact" label="Contacto de emergencia" :value="$patient->emergencyContact" maxlength="150" help="Nombre y teléfono de una persona de contacto." /></div>
                    </div>
                    <x-button icon="bi-check2" loading="Guardando…">Guardar cambios</x-button>
                </form>
            </x-card>
        </div>
    </div>
</x-app-layout>
