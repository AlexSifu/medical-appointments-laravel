<?php

namespace App\Http\Requests;

/** Marcar una cita como atendida o no asistió (médico / recepción). */
final class CloseReservationRequest extends NexaFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:500'],
            'version' => self::VERSION_RULE,
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->nullIfEmpty('note');
    }
}
