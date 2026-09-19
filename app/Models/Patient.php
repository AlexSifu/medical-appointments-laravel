<?php

namespace App\Models;

/**
 * Paciente. Persona atendida; puede o no tener cuenta de usuario (paciente ≠ usuario).
 *
 * Tabla: clin.Pacientes
 * Persistencia: api.usp_RecepcionPacienteCrear / RecepcionPacienteActualizar / PacienteActualizarPerfil
 * Datos: App\DTO\PatientData
 *
 * Representación de dominio: no es un modelo Eloquent y no persiste nada por sí misma.
 */
final class Patient
{
    public const TABLE = 'clin.Pacientes';
}
