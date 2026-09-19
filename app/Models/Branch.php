<?php

namespace App\Models;

/**
 * Sede de atención. 1—N consultorios.
 *
 * Tabla: clin.Sedes
 * Persistencia: api.usp_AdminSedeGuardar; lectura api.vw_Sedes (cacheable)
 * Datos: array de catálogo
 *
 * Representación de dominio: no es un modelo Eloquent y no persiste nada por sí misma.
 */
final class Branch
{
    public const TABLE = 'clin.Sedes';
}
