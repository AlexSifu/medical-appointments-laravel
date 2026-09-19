<?php

/*
| Cuentas demo para `php artisan clinic:seed-demo` (solo desarrollo).
| Las contraseñas viven únicamente en .env; el comando las convierte a bcrypt y nunca las imprime.
*/

$account = static fn (string $prefix): array => [
    'user' => env("DEMO_{$prefix}_USER"),
    'email' => env("DEMO_{$prefix}_EMAIL"),
    'password' => env("DEMO_{$prefix}_PASSWORD"),
];

return [
    'accounts' => [
        'superadmin' => $account('SUPERADMIN'),
        'admin' => $account('ADMIN'),
        'reception' => $account('RECEPTION'),
        'doctor' => $account('DOCTOR'),
        'patient' => $account('PATIENT'),
        'auditor' => $account('AUDITOR'),
    ],
];
