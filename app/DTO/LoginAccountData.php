<?php

namespace App\DTO;

use App\Support\Row;

/**
 * Cuenta candidata para login (api.usp_AuthObtenerUsuario). Contiene el hash, por lo que
 * solo vive dentro de AuthService durante la verificación: nunca se guarda en sesión ni se loguea.
 */
final readonly class LoginAccountData
{
    public function __construct(
        public int $userId,
        public string $username,
        public string $passwordHash,
        public string $passwordAlgorithm,
        public bool $active,
        public bool $locked,
        public int $lockSeconds,
        /** @var array<string, mixed> */
        public array $profileRow,
    ) {}

    public static function fromRow(array $row): self
    {
        $profile = $row;
        unset($profile['PasswordHash'], $profile['PasswordAlgoritmo']);

        return new self(
            userId: Row::int($row, 'UsuarioId'),
            username: Row::str($row, 'NombreUsuario'),
            passwordHash: Row::str($row, 'PasswordHash'),
            passwordAlgorithm: Row::str($row, 'PasswordAlgoritmo'),
            active: Row::bool($row, 'Activo'),
            locked: Row::bool($row, 'Bloqueado'),
            lockSeconds: Row::int($row, 'SegundosBloqueo'),
            profileRow: $profile,
        );
    }

    /** Evita volcar el hash en dumps o logs. */
    public function __debugInfo(): array
    {
        return ['userId' => $this->userId, 'username' => $this->username, 'passwordHash' => '***'];
    }
}
