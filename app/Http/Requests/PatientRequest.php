<?php

namespace App\Http\Requests;

/** Alta/edición de paciente por recepción o administración. */
final class PatientRequest extends NexaFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $updating = $this->route('patient') !== null;

        return [
            'document_type' => ['required', 'in:DNI,CE,PASAPORTE'],
            'document_number' => ['required', 'string', 'min:6', 'max:20', 'regex:/^[0-9A-Z]+$/'],
            'first_names' => ['required', 'string', 'max:100'],
            'last_names' => ['required', 'string', 'max:100'],
            'birth_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:today'],
            'sex' => ['nullable', 'in:F,M,X'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+ ()-]+$/'],
            'email' => ['nullable', 'email:rfc', 'max:150'],
            'address' => ['nullable', 'string', 'max:200'],
            'emergency_contact' => ['nullable', 'string', 'max:150'],
            'active' => ['sometimes', 'boolean'],
            'version' => $updating ? self::VERSION_RULE : ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['document_number' => strtoupper(trim((string) $this->input('document_number')))]);
        $this->nullIfEmpty('birth_date', 'sex', 'phone', 'email', 'address', 'emergency_contact');
    }
}
