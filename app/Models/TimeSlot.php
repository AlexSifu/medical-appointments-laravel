<?php

namespace App\Models;

/**
 * Horario (slot) reservable de una agenda. Puede estar bloqueado (clin.BloqueosHorario).
 *
 * Tabla: clin.HorariosMedicos
 * Persistencia: api.usp_AdminHorarioBloquear / AdminHorarioDesbloquear; lectura api.usp_AgendaHorariosEstado
 * Datos: App\DTO\TimeSlotData
 *
 * Representación de dominio: no es un modelo Eloquent y no persiste nada por sí misma.
 */
final class TimeSlot
{
    public const TABLE = 'clin.HorariosMedicos';
}
