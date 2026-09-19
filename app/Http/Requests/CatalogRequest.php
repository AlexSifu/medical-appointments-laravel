<?php

namespace App\Http\Requests;

/** Especialidad o sede (administración). */
final class CatalogRequest extends NexaFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $branch = $this->routeIs('admin.branches.*');

        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => $branch ? ['prohibited'] : ['nullable', 'string', 'max:250'],
            'code' => $branch ? ['required', 'string', 'max:20', 'regex:/^[A-Z0-9_-]+$/'] : ['prohibited'],
            'address' => $branch ? ['nullable', 'string', 'max:200'] : ['prohibited'],
            'active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['active' => $this->boolean('active')]);
        if ($this->has('code')) {
            $this->merge(['code' => strtoupper(trim((string) $this->input('code')))]);
        }
        $this->nullIfEmpty('description', 'address');
    }
}
