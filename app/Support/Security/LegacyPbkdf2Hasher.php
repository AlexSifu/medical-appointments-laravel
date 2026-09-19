<?php

namespace App\Support\Security;

/**
 * Verificación de contraseñas migradas del sistema VB.NET (Rfc2898DeriveBytes, PBKDF2-HMAC-SHA256).
 *
 * Formato almacenado en V2 (PasswordAlgoritmo = 'PBKDF2_SHA256'):
 *     pbkdf2_sha256$<iteraciones>$<salt base64>$<hash base64>
 *
 * Solo se usa para verificar en el primer login; tras un login correcto AuthService
 * vuelve a generar el hash con bcrypt (Hash::make) y lo guarda por api.usp_UsuarioActualizarPasswordHash.
 */
final class LegacyPbkdf2Hasher
{
    public const ALGORITHM = 'PBKDF2_SHA256';

    private const PREFIX = 'pbkdf2_sha256';

    private const MIN_ITERATIONS = 10_000;

    private const MAX_ITERATIONS = 1_000_000;

    public function verify(string $password, string $stored): bool
    {
        $parts = explode('$', $stored);
        if (count($parts) !== 4 || $parts[0] !== self::PREFIX || ! ctype_digit($parts[1])) {
            return false;
        }

        $iterations = (int) $parts[1];
        $salt = base64_decode($parts[2], true);
        $expected = base64_decode($parts[3], true);

        if ($iterations < self::MIN_ITERATIONS || $iterations > self::MAX_ITERATIONS
            || $salt === false || $expected === false || strlen($salt) < 16 || strlen($expected) < 16) {
            return false;
        }

        $computed = hash_pbkdf2('sha256', $password, $salt, $iterations, strlen($expected), true);

        return hash_equals($expected, $computed);
    }

    /** Genera un hash en formato legado (solo para pruebas y documentación del formato). */
    public function make(string $password, int $iterations = 100_000): string
    {
        $salt = random_bytes(32);
        $hash = hash_pbkdf2('sha256', $password, $salt, $iterations, 32, true);

        return implode('$', [self::PREFIX, $iterations, base64_encode($salt), base64_encode($hash)]);
    }
}
