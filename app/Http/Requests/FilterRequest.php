<?php

namespace App\Http\Requests;

/**
 * Filtros de listados (GET): reservas, auditoría, reportes, agendas, bloqueos, usuarios.
 * Todos opcionales; los valores se pasan como parámetros tipados a SP (nunca SQL dinámico).
 */
final class FilterRequest extends NexaFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'status_id' => ['nullable', 'integer', 'between:1,5'],
            'doctor_id' => ['nullable', 'integer', 'min:1'],
            'specialty_id' => ['nullable', 'integer', 'min:1'],
            'branch_id' => ['nullable', 'integer', 'min:1'],
            'patient_id' => ['nullable', 'integer', 'min:1'],
            'user_id' => ['nullable', 'integer', 'min:1'],
            'role' => ['nullable', 'string', 'max:30', 'regex:/^[A-Z_]+$/'],
            'active' => ['nullable', 'in:0,1'],
            'only_active' => ['nullable', 'boolean'],
            'action' => ['nullable', 'string', 'max:60', 'regex:/^[A-Z_]+$/'],
            'entity' => ['nullable', 'string', 'max:60', 'regex:/^[A-Za-z_]+$/'],
            'result' => ['nullable', 'in:ok,error'],
            'correlation_id' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9-]+$/'],
            'tab' => ['nullable', 'in:proximas,historial,canceladas'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'export' => ['nullable', 'in:csv'],
        ];
    }

    /** Filtros no vacíos ya validados. */
    public function filters(): array
    {
        return array_filter($this->validated(), static fn ($v): bool => $v !== null && $v !== '');
    }

    public function page(): int
    {
        return max(1, (int) $this->input('page', 1));
    }

    protected function prepareForValidation(): void
    {
        $this->nullIfEmpty(...array_keys($this->query()));
    }
}
