<?php

namespace App\Services;

use App\DTO\AuthenticatedUserData;
use App\DTO\LoginAccountData;
use App\Exceptions\BusinessRuleException;
use App\Models\User;
use App\Repositories\Contracts\AuthRepositoryInterface;
use App\Support\Security\LegacyPbkdf2Hasher;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Login contra SQL Server (§40):
 *   api.usp_AuthObtenerUsuario → verificar hash en PHP → AuthRegistrarExito / AuthRegistrarFallo
 *   → api.usp_AuthObtenerPermisosUsuario. Los hashes legados (PBKDF2) se migran a bcrypt en el primer login.
 *
 * Los mensajes al usuario son genéricos: no revelan si el usuario existe.
 */
final class AuthService
{
    public const GENERIC_ERROR = 'Usuario o contraseña incorrectos.';

    /** Hash bcrypt de relleno para igualar tiempos cuando el usuario no existe. */
    private const DUMMY_HASH = '$2y$12$35ATA1BHJX48YNkETBAPwueSjrB2jHkYFP/xLb/Gd0eChP8uMVn1W';

    public function __construct(
        private readonly AuthRepositoryInterface $auth,
        private readonly Hasher $hasher,
        private readonly LegacyPbkdf2Hasher $legacy,
    ) {}

    /**
     * @throws BusinessRuleException credenciales inválidas, cuenta bloqueada o inactiva
     */
    public function attempt(string $login, string $password): AuthenticatedUserData
    {
        $login = trim($login);
        $account = $this->auth->findForLogin($login);

        if ($account === null) {
            password_verify($password, self::DUMMY_HASH);
            $this->auth->registerFailure(null, $login, 'CREDENCIALES');
            throw new BusinessRuleException('CREDENCIALES_INVALIDAS', self::GENERIC_ERROR);
        }

        $valid = $this->verify($account, $password);

        if (! $valid) {
            $this->auth->registerFailure($account->userId, $login, $account->locked ? 'BLOQUEADO' : 'CREDENCIALES');
            throw new BusinessRuleException('CREDENCIALES_INVALIDAS', self::GENERIC_ERROR);
        }

        if (! $account->active) {
            $this->auth->registerFailure($account->userId, $login, 'INACTIVO');
            throw new BusinessRuleException('CREDENCIALES_INVALIDAS', self::GENERIC_ERROR);
        }

        if ($account->locked) {
            $this->auth->registerFailure($account->userId, $login, 'BLOQUEADO');
            $minutes = max(1, (int) ceil($account->lockSeconds / 60));
            throw new BusinessRuleException('CUENTA_BLOQUEADA', "Cuenta bloqueada temporalmente por intentos fallidos. Intenta nuevamente en {$minutes} min.");
        }

        $this->auth->registerSuccess($account->userId)->throwIfFailed();
        $this->upgradeHashIfNeeded($account, $password);

        return AuthenticatedUserData::fromRows($account->profileRow, $this->auth->grants($account->userId));
    }

    /** Relee roles/permisos (p. ej. tras un cambio de rol) usando la misma consulta del login. */
    public function reload(User $user): ?AuthenticatedUserData
    {
        $account = $this->auth->findForLogin($user->data->username);
        if ($account === null || ! $account->active || $account->userId !== $user->id()) {
            return null;
        }

        return AuthenticatedUserData::fromRows($account->profileRow, $this->auth->grants($account->userId));
    }

    public function logout(User $user): void
    {
        try {
            $this->auth->registerLogout($user->id());
        } catch (Throwable $e) {
            // El logout local siempre procede aunque la bitácora no esté disponible.
            Log::warning('No se pudo registrar el logout en la bitácora.', ['exception' => $e::class]);
        }
    }

    private function verify(LoginAccountData $account, string $password): bool
    {
        return match ($account->passwordAlgorithm) {
            'BCRYPT', 'ARGON2ID' => $account->passwordHash !== '' && password_verify($password, $account->passwordHash),
            LegacyPbkdf2Hasher::ALGORITHM => $this->legacy->verify($password, $account->passwordHash),
            default => false,
        };
    }

    private function upgradeHashIfNeeded(LoginAccountData $account, string $password): void
    {
        $needs = $account->passwordAlgorithm === LegacyPbkdf2Hasher::ALGORITHM
            || ($account->passwordAlgorithm === 'BCRYPT' && $this->hasher->needsRehash($account->passwordHash));

        if (! $needs) {
            return;
        }

        try {
            $result = $this->auth->updatePasswordHash($account->userId, $account->userId, $this->hasher->make($password), 'BCRYPT');
            if (! $result->success) {
                Log::warning('No se pudo actualizar el hash de contraseña.', ['code' => $result->code, 'user_id' => $account->userId]);
            }
        } catch (Throwable $e) {
            // La migración del hash no debe impedir el login: se reintenta en el siguiente acceso.
            Log::warning('Error al migrar el hash de contraseña.', ['exception' => $e::class, 'user_id' => $account->userId]);
        }
    }
}
