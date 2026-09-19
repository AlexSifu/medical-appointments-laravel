<?php

namespace App\Policies;

use App\DTO\ReservationData;
use App\Models\Permission;
use App\Models\User;

/**
 * Decide qué acciones mostrar en la interfaz para una reserva. Es solo presentación:
 * el SP correspondiente vuelve a validar permiso, pertenencia, estado y plazos.
 */
final class ReservationPolicy
{
    public function view(User $user, ReservationData $reservation): bool
    {
        return $user->can(Permission::RESERVATIONS_MANAGE)
            || $this->owns($user, $reservation)
            || $this->treats($user, $reservation);
    }

    public function cancel(User $user, ReservationData $reservation): bool
    {
        return $reservation->canCancel
            && $user->can(Permission::RESERVATIONS_CANCEL)
            && ($user->can(Permission::RESERVATIONS_MANAGE) || $this->owns($user, $reservation));
    }

    public function reschedule(User $user, ReservationData $reservation): bool
    {
        return $reservation->canReschedule
            && $user->can(Permission::RESERVATIONS_RESCHEDULE)
            && ($user->can(Permission::RESERVATIONS_MANAGE) || $this->owns($user, $reservation));
    }

    public function close(User $user, ReservationData $reservation): bool
    {
        return $reservation->canClose
            && $user->can(Permission::RESERVATIONS_STATUS)
            && ($user->can(Permission::RESERVATIONS_MANAGE) || $this->treats($user, $reservation));
    }

    private function owns(User $user, ReservationData $reservation): bool
    {
        return $user->data->patientId !== null && $reservation->patientId === $user->data->patientId;
    }

    private function treats(User $user, ReservationData $reservation): bool
    {
        return $user->data->doctorId !== null && $reservation->doctorId === $user->data->doctorId;
    }
}
