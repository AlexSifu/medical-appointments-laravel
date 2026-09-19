<?php

namespace App\Exceptions;

use App\Support\ErrorMessages;
use RuntimeException;
use Throwable;

/** Bloqueo no obtenido (50409), deadlock (1205) o lock timeout (1222). La operación puede reintentarse. */
class ConcurrencyException extends RuntimeException
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct(ErrorMessages::for('CONCURRENCIA'), 0, $previous);
    }
}
