<?php

namespace App\Support;

/**
 * Textos de UX para los códigos de negocio devueltos por SQL Server.
 * La regla vive en SQL; aquí solo se decide cómo se comunica al usuario.
 *
 *  - OVERRIDES: siempre se usa el texto de la aplicación (mensajes estándar de la guía de UX).
 *  - DEFAULTS:  se usan solo si el procedimiento no devolvió mensaje (o para excepciones PHP).
 */
final class ErrorMessages
{
    private const OVERRIDES = [
        'SLOT_OCUPADO' => 'El horario acaba de ser reservado por otra persona. Elige otro horario.',
        'CONFLICTO_EDICION' => 'El registro fue modificado por otro usuario. Recarga la página e inténtalo de nuevo.',
        'CUENTA_NO_DISPONIBLE' => 'Usuario o contraseña incorrectos.',
        'ERROR_INTERNO' => 'Ocurrió un error inesperado. Intenta nuevamente o contacta al administrador.',
    ];

    private const DEFAULTS = [
        'SIN_PERMISO' => 'No tienes permiso para realizar esta acción.',
        'ESTADO_INVALIDO' => 'La cita ya fue cancelada o cerrada.',
        'FUERA_DE_PLAZO' => 'No puedes cancelar con tan poca anticipación.',
        'CONCURRENCIA' => 'Otra operación está usando este recurso. Intenta nuevamente en unos segundos.',
        'SIN_FECHAS' => 'No existen horarios disponibles.',
        'NO_ENCONTRADO' => 'El registro solicitado no existe o no tienes acceso a él.',
        'BD_NO_DISPONIBLE' => 'El servicio no está disponible en este momento. Intenta nuevamente en unos minutos.',
        'CREDENCIALES_INVALIDAS' => 'Usuario o contraseña incorrectos.',
    ];

    public static function for(string $code, ?string $fallback = null): string
    {
        if (isset(self::OVERRIDES[$code])) {
            return self::OVERRIDES[$code];
        }
        if ($fallback !== null && trim($fallback) !== '') {
            return $fallback;
        }

        return self::DEFAULTS[$code] ?? self::OVERRIDES['ERROR_INTERNO'];
    }
}
