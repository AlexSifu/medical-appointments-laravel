<?php

namespace App\Models;

/**
 * Reserva / cita (clin.Reservas).
 *
 * Relaciones: Paciente 1—N Reserva; HorarioMedico 1—N Reserva (a lo sumo una ocupante:
 * índice único filtrado UX_Reservas_HorarioOcupado); Reserva origen → reemplazo al reprogramar;
 * Reserva 1—N HistorialReserva.
 * Persistencia: api.usp_ReservaCrear / RecepcionReservaCrear / ReservaCancelar / ReservaReprogramar /
 * MedicoMarcarAtendida / MedicoMarcarNoAsistio (ver ReservationRepository).
 * Datos: App\DTO\ReservationData.
 *
 * Representación de dominio: no es un modelo Eloquent y no persiste nada por sí misma.
 */
final class Reservation
{
    public const TABLE = 'clin.Reservas';

    /** Motivos de cancelación (clin.MotivosCancelacion). OTRO exige observación. */
    public const REASON_OTHER = 6;

    public const REASON_STAFF_ONLY = [4, 5];
}
