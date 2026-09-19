<?php

namespace App\Http\Middleware;

use App\Exceptions\PermissionDeniedException;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * permission:a,b → exige al menos uno de los permisos (OR). Es una primera barrera de UX:
 * SQL Server vuelve a validar el permiso dentro de cada SP.
 */
final class EnsurePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->canAny(...$permissions)) {
            throw new PermissionDeniedException;
        }

        return $next($request);
    }
}
