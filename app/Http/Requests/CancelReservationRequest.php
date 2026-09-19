<?php

namespace App\Http\Requests;

final class CancelReservationRequest extends NexaFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason_id' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:500'],
            'version' => self::VERSION_RULE,
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->nullIfEmpty('notes');
    }
}
