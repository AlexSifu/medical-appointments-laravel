<?php

use App\Exceptions\BusinessRuleException;
use App\Exceptions\ConcurrencyException;
use App\Exceptions\DatabaseUnavailableException;
use App\Exceptions\NotFoundException;
use App\Exceptions\PermissionDeniedException;
use App\Http\Middleware\AssignCorrelationId;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\RefreshAuthenticatedUser;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: [__DIR__.'/../routes/web.php', __DIR__.'/../routes/auth.php'],
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AssignCorrelationId::class);
        $middleware->append(SecurityHeaders::class);

        $middleware->alias([
            'permission' => EnsurePermission::class,
            'refresh.user' => RefreshAuthenticatedUser::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Reglas de negocio y denegaciones son resultados esperados: ya quedan en la bitácora SQL.
        $exceptions->dontReport([
            BusinessRuleException::class,
            ConcurrencyException::class,
            PermissionDeniedException::class,
            NotFoundException::class,
        ]);

        $wantsJson = static fn (Request $request): bool => $request->expectsJson();

        $back = static function (Request $request, string $message, string $code) {
            $target = $request->isMethod('GET') ? redirect()->route('dashboard') : back();

            return $target->withInput($request->except(['password', 'password_confirmation', '_token']))
                ->with('error', $message)
                ->with('error_code', $code);
        };

        $exceptions->render(function (BusinessRuleException $e, Request $request) use ($wantsJson, $back) {
            return $wantsJson($request)
                ? response()->json(['code' => $e->businessCode, 'message' => $e->getMessage()], 422)
                : $back($request, $e->getMessage(), $e->businessCode);
        });

        $exceptions->render(function (ConcurrencyException $e, Request $request) use ($wantsJson, $back) {
            return $wantsJson($request)
                ? response()->json(['code' => 'CONCURRENCIA', 'message' => $e->getMessage()], 409)
                : $back($request, $e->getMessage(), 'CONCURRENCIA');
        });

        $exceptions->render(function (PermissionDeniedException $e, Request $request) use ($wantsJson) {
            return $wantsJson($request)
                ? response()->json(['code' => 'SIN_PERMISO', 'message' => $e->getMessage()], 403)
                : response()->view('errors.403', ['message' => $e->getMessage()], 403);
        });

        $exceptions->render(function (NotFoundException $e, Request $request) use ($wantsJson) {
            return $wantsJson($request)
                ? response()->json(['code' => 'NO_ENCONTRADO', 'message' => $e->getMessage()], 404)
                : response()->view('errors.404', ['message' => $e->getMessage()], 404);
        });

        $exceptions->render(function (DatabaseUnavailableException $e, Request $request) use ($wantsJson) {
            return $wantsJson($request)
                ? response()->json(['code' => 'BD_NO_DISPONIBLE', 'message' => $e->getMessage()], 503)
                : response()->view('errors.503', ['message' => $e->getMessage()], 503);
        });
    })->create();
