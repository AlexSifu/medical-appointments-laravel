<?php

namespace App\Http\Requests;

final class CreateReservationRequest extends NexaFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'slot_id' => ['required', 'integer', 'min:1'],
            'patient_id' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->nullIfEmpty('notes', 'patient_id');
    }
}
