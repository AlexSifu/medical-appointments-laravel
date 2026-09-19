<?php

namespace App\Models;

/**
 * Especialidad médica.
 *
 * Tabla: clin.Especialidades
 * Persistencia: api.usp_AdminEspecialidadGuardar; lectura api.vw_Especialidades (cacheable)
 * Datos: array de catálogo
 *
 * Representación de dominio: no es un modelo Eloquent y no persiste nada por sí misma.
 */
final class Specialty
{
    public const TABLE = 'clin.Especialidades';
}
