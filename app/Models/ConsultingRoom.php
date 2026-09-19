<?php

namespace App\Models;

/**
 * Consultorio de una sede. No admite agendas solapadas.
 *
 * Tabla: clin.Consultorios
 * Persistencia: api.usp_AdminConsultorioGuardar; lectura api.vw_Consultorios
 * Datos: array de catálogo
 *
 * Representación de dominio: no es un modelo Eloquent y no persiste nada por sí misma.
 */
final class ConsultingRoom
{
    public const TABLE = 'clin.Consultorios';
}
