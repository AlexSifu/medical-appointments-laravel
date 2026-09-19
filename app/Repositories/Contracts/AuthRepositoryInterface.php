<?php

namespace App\Repositories\Contracts;

use App\DTO\LoginAccountData;
use App\DTO\ProcedureResult;

interface AuthRepositoryInterface
{
    public function findForLogin(string $login): ?LoginAccountData;

    /** @return list<array{Tipo: string, Codigo: string}> */
    public function grants(int $userId): array;

    public function registerSuccess(int $userId): ProcedureResult;

    /** @param 'CREDENCIALES'|'BLOQUEADO'|'INACTIVO' $reason */
    public function registerFailure(?int $userId, string $login, string $reason): ProcedureResult;

    public function registerLogout(int $userId): ProcedureResult;

    public function updatePasswordHash(int $actorId, int $userId, string $hash, string $algorithm): ProcedureResult;
}
