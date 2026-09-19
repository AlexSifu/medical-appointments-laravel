<?php

namespace App\Repositories\SqlServer;

use App\DTO\LoginAccountData;
use App\DTO\ProcedureResult;
use App\Repositories\Contracts\AuthRepositoryInterface;

final class SqlServerAuthRepository extends SqlServerRepository implements AuthRepositoryInterface
{
    private const GET_USER = 'api.usp_AuthObtenerUsuario';

    private const GET_GRANTS = 'api.usp_AuthObtenerPermisosUsuario';

    private const REGISTER_SUCCESS = 'api.usp_AuthRegistrarExito';

    private const REGISTER_FAILURE = 'api.usp_AuthRegistrarFallo';

    private const REGISTER_LOGOUT = 'api.usp_AuthRegistrarLogout';

    private const UPDATE_HASH = 'api.usp_UsuarioActualizarPasswordHash';

    public function findForLogin(string $login): ?LoginAccountData
    {
        $row = $this->sql->selectOne(self::GET_USER, ['Login' => $login]);

        return $row === null ? null : LoginAccountData::fromRow($row);
    }

    public function grants(int $userId): array
    {
        return $this->sql->select(self::GET_GRANTS, ['UsuarioId' => $userId]);
    }

    public function registerSuccess(int $userId): ProcedureResult
    {
        return $this->sql->command(self::REGISTER_SUCCESS, ['UsuarioId' => $userId]);
    }

    public function registerFailure(?int $userId, string $login, string $reason): ProcedureResult
    {
        return $this->sql->command(self::REGISTER_FAILURE, [
            'UsuarioId' => $userId,
            'Login' => mb_substr($login, 0, 150),
            'Motivo' => $reason,
        ]);
    }

    public function registerLogout(int $userId): ProcedureResult
    {
        return $this->sql->command(self::REGISTER_LOGOUT, ['UsuarioId' => $userId]);
    }

    public function updatePasswordHash(int $actorId, int $userId, string $hash, string $algorithm): ProcedureResult
    {
        return $this->sql->command(self::UPDATE_HASH, [
            'ActorUsuarioId' => $actorId,
            'UsuarioId' => $userId,
            'PasswordHash' => $hash,
            'PasswordAlgoritmo' => $algorithm,
        ]);
    }
}
