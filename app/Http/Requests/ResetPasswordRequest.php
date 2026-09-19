<?php

namespace App\Http\Requests;

final class ResetPasswordRequest extends NexaFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['password' => ['required', 'confirmed', UserRequest::passwordRule()]];
    }
}
