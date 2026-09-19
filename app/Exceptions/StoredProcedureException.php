<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/** Error técnico inesperado al ejecutar un procedimiento. Se registra en el log con el correlation id. */
class StoredProcedureException extends RuntimeException
{
    public function __construct(
        public readonly string $procedure,
        public readonly ?int $sqlErrorNumber,
        Throwable $previous,
    ) {
        parent::__construct("Error al ejecutar {$procedure} (SQL {$sqlErrorNumber})", 0, $previous);
    }
}
