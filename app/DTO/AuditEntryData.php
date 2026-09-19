<?php

namespace App\DTO;

use App\Support\Row;

/** Evento de la bitácora (api.usp_AuditoriaBuscar). Solo lectura. */
final readonly class AuditEntryData
{
    public function __construct(
        public int $id,
        public string $dateUtc,
        public ?string $dateLocal,
        public ?int $userId,
        public ?string $username,
        public string $action,
        public ?string $entity,
        public ?int $entityId,
        public bool $success,
        public ?string $detail,
        public ?string $ip,
        public ?string $userAgent,
        public ?string $correlationId,
    ) {}

    public static function fromRow(array $row): self
    {
        return new self(
            id: Row::int($row, 'BitacoraId'),
            dateUtc: Row::str($row, 'FechaUtc'),
            dateLocal: Row::strOrNull($row, 'FechaLocal'),
            userId: Row::intOrNull($row, 'UsuarioId'),
            username: Row::strOrNull($row, 'NombreUsuario'),
            action: Row::str($row, 'Accion'),
            entity: Row::strOrNull($row, 'Entidad'),
            entityId: Row::intOrNull($row, 'EntidadId'),
            success: Row::bool($row, 'Exitoso'),
            detail: Row::strOrNull($row, 'Detalle'),
            ip: Row::strOrNull($row, 'Ip'),
            userAgent: Row::strOrNull($row, 'UserAgent'),
            correlationId: Row::strOrNull($row, 'CorrelationId'),
        );
    }
}
