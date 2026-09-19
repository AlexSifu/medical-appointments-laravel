<?php

namespace App\Providers;

use App\DTO\ReservationData;
use App\Models\User;
use App\Policies\ReservationPolicy;
use App\Repositories\Contracts\AuditRepositoryInterface;
use App\Repositories\Contracts\AuthRepositoryInterface;
use App\Repositories\Contracts\CatalogRepositoryInterface;
use App\Repositories\Contracts\DoctorRepositoryInterface;
use App\Repositories\Contracts\PatientRepositoryInterface;
use App\Repositories\Contracts\ReportRepositoryInterface;
use App\Repositories\Contracts\ReservationRepositoryInterface;
use App\Repositories\Contracts\ScheduleRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\SqlServer\SqlServerAuditRepository;
use App\Repositories\SqlServer\SqlServerAuthRepository;
use App\Repositories\SqlServer\SqlServerCatalogRepository;
use App\Repositories\SqlServer\SqlServerDoctorRepository;
use App\Repositories\SqlServer\SqlServerPatientRepository;
use App\Repositories\SqlServer\SqlServerReportRepository;
use App\Repositories\SqlServer\SqlServerReservationRepository;
use App\Repositories\SqlServer\SqlServerScheduleRepository;
use App\Repositories\SqlServer\SqlServerUserRepository;
use App\Support\Auth\SessionUserGuard;
use App\Support\Database\StoredProcedureExecutor;
use App\Support\RequestContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $scopedBindings = [
        AuthRepositoryInterface::class => SqlServerAuthRepository::class,
        UserRepositoryInterface::class => SqlServerUserRepository::class,
        PatientRepositoryInterface::class => SqlServerPatientRepository::class,
        DoctorRepositoryInterface::class => SqlServerDoctorRepository::class,
        ScheduleRepositoryInterface::class => SqlServerScheduleRepository::class,
        ReservationRepositoryInterface::class => SqlServerReservationRepository::class,
        AuditRepositoryInterface::class => SqlServerAuditRepository::class,
        ReportRepositoryInterface::class => SqlServerReportRepository::class,
        CatalogRepositoryInterface::class => SqlServerCatalogRepository::class,
    ];

    public function register(): void
    {
        // Contexto (IP, User-Agent, correlation id) y ejecutor: una instancia por request.
        $this->app->scoped(RequestContext::class);
        $this->app->scoped(StoredProcedureExecutor::class);

        foreach ($this->scopedBindings as $contract => $implementation) {
            $this->app->scoped($contract, $implementation);
        }
    }

    public function boot(): void
    {
        Gate::policy(ReservationData::class, ReservationPolicy::class);

        Auth::extend('nexa-session', fn ($app) => new SessionUserGuard($app['session.store']));

        // Los permisos efectivos provienen de SQL Server (api.usp_AuthObtenerPermisosUsuario).
        Gate::before(function ($user, string $ability) {
            if ($user instanceof User && str_contains($ability, '.')) {
                return $user->can($ability) ?: null;
            }

            return null;
        });

        RateLimiter::for('login', function (Request $request) {
            $login = Str::lower((string) $request->input('login'));

            return [
                Limit::perMinute(5)->by('login|'.$login.'|'.$request->ip()),
                Limit::perMinute(20)->by('login-ip|'.$request->ip()),
            ];
        });

        Paginator::useBootstrapFive();
    }
}
