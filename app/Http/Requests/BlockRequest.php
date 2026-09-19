<?php

namespace App\Http\Requests;

/** Bloqueo de un horario puntual (slot_id) o de un rango del médico. */
final class BlockRequest extends NexaFormRequest
{
    public const TYPES = [
        'AUSENCIA' => 'Ausencia',
        'REUNION' => 'Reunión',
        'CAPACITACION' => 'Capacitación',
        'LICENCIA' => 'Licencia',
        'MANTENIMIENTO' => 'Mantenimiento',
        'SLOT' => 'Horario puntual',
    ];

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $single = $this->filled('slot_id');

        return [
            'doctor_id' => ['required', 'integer', 'min:1'],
            'slot_id' => ['nullable', 'integer', 'min:1'],
            'type' => ['required', 'in:'.implode(',', array_keys(self::TYPES))],
            'date' => $single ? ['nullable'] : ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'start' => $single ? ['nullable'] : self::TIME_RULE,
            'end' => $single ? ['nullable'] : ['required', 'date_format:H:i', 'after:start'],
            'reason' => ['required', 'string', 'min:3', 'max:250'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->nullIfEmpty('slot_id', 'date', 'start', 'end');
    }
}
