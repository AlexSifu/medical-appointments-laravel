<?php

namespace App\Exceptions;

use App\Support\ErrorMessages;
use RuntimeException;

/** Registro inexistente o no visible para el actor (el SP no devolvió filas). */
class NotFoundException extends RuntimeException
{
    public function __construct(?string $message = null)
    {
        parent::__construct($message ?? ErrorMessages::for('NO_ENCONTRADO'));
    }
}
