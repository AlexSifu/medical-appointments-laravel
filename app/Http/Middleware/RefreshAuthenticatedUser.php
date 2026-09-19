<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\AuthService;
use App\Support\Auth\SessionUserGuard;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Relee roles/permisos desde SQL Server cada REFRESH_SECONDS: un usuario desactivado o
 * con roles modificados no conserva privilegios antiguos durante toda la sesión.
 */
final class RefreshAuthenticatedUser
{
    public const REFRESH_SECONDS = 300;

    public function __construct(private readonly AuthService $auth) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && time() - $user->data->loadedAt > self::REFRESH_SECONDS) {
            /** @var SessionUserGuard $guard */
            $guard = Auth::guard();
            $fresh = $this->auth->reload($user);

            if ($fresh === null) {
                $guard->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->with('status', 'Tu sesión finalizó. Ingresa nuevamente.');
            }

            $guard->login(new User($fresh));
        }

        return $next($request);
    }
}
