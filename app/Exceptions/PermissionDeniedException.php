<?php

namespace App\Exceptions;

use App\Support\ErrorMessages;
use RuntimeException;

/** Error SQL 50403 (SIN_PERMISO) lanzado por un SP de consulta, o denegación preventiva en Laravel. */
class PermissionDeniedException extends RuntimeException
{
    public function __construct(?string $message = null)
    {
        parent::__construct($message ?? ErrorMessages::for('SIN_PERMISO'));
    }
}
