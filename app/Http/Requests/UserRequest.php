<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Validation\Rules\Password;

/** Alta/edición de usuarios (administración). La contraseña se hashea en Laravel. */
final class UserRequest extends NexaFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        if ($this->route('user') !== null) {
            return [
                'email' => ['nullable', 'email:rfc', 'max:150'],
                'first_names' => ['required', 'string', 'max:100'],
                'last_names' => ['required', 'string', 'max:100'],
                'active' => ['required', 'boolean'],
                'version' => self::VERSION_RULE,
            ];
        }

        return [
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/'],
            'email' => ['nullable', 'email:rfc', 'max:150'],
            'first_names' => ['required', 'string', 'max:100'],
            'last_names' => ['required', 'string', 'max:100'],
            'role' => ['required', 'in:'.implode(',', array_keys(Role::LABELS))],
            'doctor_id' => ['nullable', 'integer', 'min:1'],
            'patient_id' => ['nullable', 'integer', 'min:1'],
            'password' => ['required', 'confirmed', self::passwordRule()],
        ];
    }

    public static function passwordRule(): Password
    {
        return Password::min(10)->letters()->mixedCase()->numbers()->max(128);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['active' => $this->boolean('active')]);
        $this->nullIfEmpty('email', 'doctor_id', 'patient_id');
    }
}
