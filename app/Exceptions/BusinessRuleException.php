<?php

namespace App\Exceptions;

use App\DTO\ProcedureResult;
use RuntimeException;

/**
 * Rechazo de negocio devuelto por un procedimiento almacenado (Exito = 0).
 * No es un error técnico: se muestra al usuario y no se registra como excepción.
 */
class BusinessRuleException extends RuntimeException
{
    public function __construct(
        public readonly string $businessCode,
        string $message,
        public readonly ?ProcedureResult $result = null,
    ) {
        parent::__construct($message);
    }

    public static function fromResult(ProcedureResult $result): self
    {
        return new self($result->code, $result->userMessage(), $result);
    }
}
