<?php

namespace App\Models;

/**
 * Médico. N especialidades (clin.MedicoEspecialidad) y N sedes (clin.MedicoSede). Sin borrado físico: se desactiva.
 *
 * Tabla: clin.Medicos
 * Persistencia: api.usp_AdminMedicoCrear / AdminMedicoActualizar / AdminMedicoCambiarEstado / AdminMedicoAsignarEspecialidad / AdminMedicoAsignarSede
 * Datos: App\DTO\DoctorData
 *
 * Representación de dominio: no es un modelo Eloquent y no persiste nada por sí misma.
 */
final class Doctor
{
    public const TABLE = 'clin.Medicos';
}
