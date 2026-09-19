{{-- Página de error autónoma: no depende de sesión, permisos ni base de datos. --}}
@php
    // Solo mensajes explícitos de la aplicación (ya en español y sin detalles técnicos).
    $text = ($message ?? null) ?: $default;
    $correlation = rescue(fn () => app(\App\Support\RequestContext::class)->correlationId(), null, false);
    $home = rescue(fn () => auth()->check() ? route('dashboard') : route('login'), url('/'), false);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $code }} · {{ $title }} · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-body-tertiary">
    <main class="min-vh-100 d-flex align-items-center justify-content-center p-3">
        <div class="card shadow-sm text-center" style="max-width: 32rem; width: 100%">
            <div class="card-body p-4 p-md-5">
                <div class="display-6 mb-3 text-{{ $tone ?? 'primary' }}" aria-hidden="true"><i class="bi {{ $icon }}"></i></div>
                <p class="text-secondary fw-semibold mb-1">Error {{ $code }}</p>
                <h1 class="h4 mb-3">{{ $title }}</h1>
                <p class="text-secondary">{{ $text }}</p>
                <div class="d-flex flex-wrap gap-2 justify-content-center mt-4">
                    <a href="{{ $home }}" class="btn btn-primary"><i class="bi bi-house me-1" aria-hidden="true"></i>Ir al inicio</a>
                    @if ($code !== 419)
                        <a href="{{ url()->previous() }}" class="btn btn-outline-secondary">Volver</a>
                    @else
                        <a href="{{ route('login') }}" class="btn btn-outline-secondary">Iniciar sesión</a>
                    @endif
                </div>
                @if ($correlation)
                    <p class="small text-secondary mt-4 mb-0">Código de seguimiento: <span class="code-pill">{{ $correlation }}</span></p>
                @endif
            </div>
        </div>
    </main>
</body>
</html>
