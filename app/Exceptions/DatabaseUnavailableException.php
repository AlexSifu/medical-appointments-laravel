<?php

namespace App\Exceptions;

use App\Support\ErrorMessages;
use RuntimeException;
use Throwable;

/** SQL Server no accesible (conexión, login, timeout). */
class DatabaseUnavailableException extends RuntimeException
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct(ErrorMessages::for('BD_NO_DISPONIBLE'), 0, $previous);
    }
}
