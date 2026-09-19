<?php

namespace App\Models;

/**
 * Evento de bitácora. Solo inserción desde SQL; nunca contiene passwords ni tokens.
 *
 * Tabla: audit.BitacoraSistema
 * Persistencia: escrita únicamente por los procedimientos; lectura api.usp_AuditoriaBuscar
 * Datos: App\DTO\AuditEntryData
 *
 * Representación de dominio: no es un modelo Eloquent y no persiste nada por sí misma.
 */
final class AuditLog
{
    public const TABLE = 'audit.BitacoraSistema';
}
