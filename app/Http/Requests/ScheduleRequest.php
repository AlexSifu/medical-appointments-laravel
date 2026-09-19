<?php

namespace App\Http\Requests;

/**
 * Crear agenda de un día (mode=single) o generar un rango (mode=range, máx. 92 días).
 * Solapamientos, consultorio ocupado y horas válidas los valida el SP.
 */
final class ScheduleRequest extends NexaFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $range = $this->input('mode') === 'range';

        return [
            'mode' => ['required', 'in:single,range'],
            'doctor_id' => ['required', 'integer', 'min:1'],
            'specialty_id' => ['required', 'integer', 'min:1'],
            'branch_id' => ['required', 'integer', 'min:1'],
            'room_id' => ['required', 'integer', 'min:1'],
            'care_type_id' => ['required', 'integer', 'min:1'],
            'date' => $range ? ['nullable'] : ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'date_from' => $range ? ['required', 'date_format:Y-m-d', 'after_or_equal:today'] : ['nullable'],
            'date_to' => $range ? ['required', 'date_format:Y-m-d', 'after_or_equal:date_from'] : ['nullable'],
            'weekdays' => $range ? ['required', 'array', 'min:1'] : ['nullable'],
            'weekdays.*' => ['integer', 'between:1,7', 'distinct'],
            'start' => self::TIME_RULE,
            'end' => ['required', 'date_format:H:i', 'after:start'],
            'duration' => ['required', 'integer', 'between:10,120'],
        ];
    }
}
