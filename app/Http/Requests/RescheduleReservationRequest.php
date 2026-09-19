<?php

namespace App\Http\Requests;

final class RescheduleReservationRequest extends NexaFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'new_slot_id' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:500'],
            'version' => self::VERSION_RULE,
            'idempotency_key' => ['required', 'uuid'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->nullIfEmpty('reason');
    }
}
