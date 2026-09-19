<?php

namespace App\Http\Requests;

final class DoctorStatusRequest extends NexaFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'active' => ['required', 'boolean'],
            'version' => self::VERSION_RULE,
        ];
    }
}
