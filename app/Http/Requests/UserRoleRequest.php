<?php

namespace App\Http\Requests;

use App\Models\Role;

final class UserRoleRequest extends NexaFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'role' => ['required', 'in:'.implode(',', array_keys(Role::LABELS))],
            'assign' => ['required', 'boolean'],
        ];
    }
}
