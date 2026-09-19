<?php

namespace App\Support\Auth;

use App\DTO\AuthenticatedUserData;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Session\Session;
use InvalidArgumentException;
use Throwable;

/**
 * Guard de sesión sin Eloquent: la identidad se valida contra SQL Server (AuthService) y
 * en sesión solo se guarda AuthenticatedUserData (sin hash de contraseña).
 */
final class SessionUserGuard implements Guard
{
    public const SESSION_KEY = 'nexa_auth_user';

    private ?User $user = null;

    public function __construct(private readonly Session $session) {}

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function guest(): bool
    {
        return ! $this->check();
    }

    public function user(): ?User
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $data = $this->session->get(self::SESSION_KEY);
        if (! is_array($data)) {
            return null;
        }

        try {
            return $this->user = new User(AuthenticatedUserData::fromArray($data));
        } catch (Throwable) {
            $this->session->forget(self::SESSION_KEY);

            return null;
        }
    }

    public function id(): ?int
    {
        return $this->user()?->id();
    }

    /** Las credenciales se validan en AuthService contra api.usp_AuthObtenerUsuario. */
    public function validate(array $credentials = []): bool
    {
        return false;
    }

    public function hasUser(): bool
    {
        return $this->user !== null;
    }

    public function setUser(Authenticatable $user): static
    {
        if (! $user instanceof User) {
            throw new InvalidArgumentException('Tipo de usuario no soportado.');
        }
        $this->user = $user;

        return $this;
    }

    public function login(User $user): void
    {
        $this->session->put(self::SESSION_KEY, $user->data->toArray());
        $this->user = $user;
    }

    public function logout(): void
    {
        $this->session->forget(self::SESSION_KEY);
        $this->user = null;
    }
}
