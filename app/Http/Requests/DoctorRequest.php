<?php

namespace App\Http\Requests;

final class DoctorRequest extends NexaFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $updating = $this->route('doctor') !== null;

        return [
            'cmp' => ['required', 'string', 'max:20', 'regex:/^[0-9A-Z-]+$/'],
            'first_names' => ['required', 'string', 'max:100'],
            'last_names' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+ ()-]+$/'],
            'email' => ['nullable', 'email:rfc', 'max:150'],
            'specialty_id' => $updating ? ['prohibited'] : ['required', 'integer', 'min:1'],
            'branch_id' => $updating ? ['prohibited'] : ['required', 'integer', 'min:1'],
            'version' => $updating ? self::VERSION_RULE : ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['cmp' => strtoupper(trim((string) $this->input('cmp')))]);
        $this->nullIfEmpty('phone', 'email');
    }
}
