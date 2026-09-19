<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Autenticación
    |--------------------------------------------------------------------------
    |
    | Nexa Salud no usa Eloquent para usuarios. El guard "nexa-session"
    | (App\Support\Auth\SessionUserGuard, registrado en AppServiceProvider)
    | guarda en sesión la identidad y permisos leídos de SQL Server por
    | api.usp_AuthObtenerUsuario / api.usp_AuthObtenerPermisosUsuario.
    | La verificación de contraseña ocurre en App\Services\AuthService.
    |
    | No hay recuperación de contraseña por correo en este alcance: la
    | contraseña la restablece un administrador (usuarios.editar).
    |
    */

    'defaults' => [
        'guard' => 'web',
    ],

    'guards' => [
        'web' => [
            'driver' => 'nexa-session',
        ],
    ],

    'providers' => [],

    'passwords' => [],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
