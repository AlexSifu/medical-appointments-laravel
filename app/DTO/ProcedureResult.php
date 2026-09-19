<?php

namespace App\DTO;

use App\Exceptions\BusinessRuleException;
use App\Support\ErrorMessages;
use App\Support\Row;

/**
 * Fila estándar devuelta por los comandos SQL: Exito | Codigo | Mensaje | EntidadId.
 */
final readonly class ProcedureResult
{
    public function __construct(
        public bool $success,
        public string $code,
        public string $message,
        public ?int $entityId = null,
    ) {}

    public static function fromRow(array $row): self
    {
        return new self(
            success: Row::bool($row, 'Exito'),
            code: Row::str($row, 'Codigo', 'ERROR_INTERNO'),
            message: Row::str($row, 'Mensaje'),
            entityId: Row::intOrNull($row, 'EntidadId'),
        );
    }

    /** Mensaje para la interfaz (textos de UX del proyecto, con respaldo en el mensaje del SP). */
    public function userMessage(): string
    {
        return ErrorMessages::for($this->code, $this->message);
    }

    /** Lanza BusinessRuleException si el SP rechazó la operación. */
    public function throwIfFailed(): self
    {
        if (! $this->success) {
            throw BusinessRuleException::fromResult($this);
        }

        return $this;
    }
}
