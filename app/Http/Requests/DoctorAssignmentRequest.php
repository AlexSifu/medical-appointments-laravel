<?php

namespace App\Http\Requests;

/** Asignar/quitar especialidad o sede a un médico. */
final class DoctorAssignmentRequest extends NexaFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'kind' => ['required', 'in:specialty,branch'],
            'target_id' => ['required', 'integer', 'min:1'],
            'assign' => ['required', 'boolean'],
            'main' => ['sometimes', 'boolean'],
        ];
    }
}
