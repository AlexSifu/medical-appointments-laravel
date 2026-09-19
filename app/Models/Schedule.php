<?php

namespace App\Models;

/**
 * Agenda diaria de un médico (especialidad + sede + consultorio + tipo de atención + rango horario). Genera N horarios.
 *
 * Tabla: clin.AgendasMedicas
 * Persistencia: api.usp_AdminAgendaCrear / AdminAgendaGenerarRango / AdminAgendaDesactivar
 * Datos: App\DTO\AgendaData
 *
 * Representación de dominio: no es un modelo Eloquent y no persiste nada por sí misma.
 */
final class Schedule
{
    public const TABLE = 'clin.AgendasMedicas';
}
