<?php

namespace App\Http\Requests;

/** Datos de contacto que el paciente puede editar de su propio perfil. */
final class ProfileRequest extends NexaFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+ ()-]+$/'],
            'email' => ['nullable', 'email:rfc', 'max:150'],
            'address' => ['nullable', 'string', 'max:200'],
            'emergency_contact' => ['nullable', 'string', 'max:150'],
            'version' => self::VERSION_RULE,
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->nullIfEmpty('phone', 'email', 'address', 'emergency_contact');
    }
}
