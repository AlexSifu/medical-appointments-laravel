<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base de los FormRequest: valida forma y tipos. Las reglas de negocio (disponibilidad,
 * límites, transiciones de estado, permisos finos) las decide SQL Server.
 */
abstract class NexaFormRequest extends FormRequest
{
    /** rowversion serializado por pdo_sqlsrv como "0x" + 16 hex. */
    protected const VERSION_RULE = ['required', 'string', 'regex:/^0x[0-9A-Fa-f]{16}$/'];

    protected const TIME_RULE = ['required', 'date_format:H:i'];

    /** La autorización por permiso la hace el middleware "permission" de la ruta. */
    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    public function actor(): User
    {
        /** @var User */
        return $this->user();
    }

    /** Convierte cadenas vacías en null para los campos indicados. */
    protected function nullIfEmpty(string ...$fields): void
    {
        $clean = [];
        foreach ($fields as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $value = trim($value);
                $clean[$field] = $value === '' ? null : $value;
            }
        }
        $this->merge($clean);
    }
}
