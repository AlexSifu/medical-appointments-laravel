@props(['title' => 'Iniciar sesión'])
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }} · Nexa Salud</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Crect width='16' height='16' rx='4' fill='%230F2742'/%3E%3Cpath d='M7 4h2v3h3v2H9v3H7V9H4V7h3z' fill='%23fff'/%3E%3C/svg%3E">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<a class="skip-link" href="#main-content">Saltar al contenido principal</a>
<div class="auth-wrap">
    <aside class="auth-aside d-none d-lg-flex flex-column justify-content-between" aria-label="Presentación">
        <div class="d-flex align-items-center gap-2">
            <span class="brand-mark" aria-hidden="true"><i class="bi bi-plus-lg"></i></span>
            <span>
                <span class="brand-name d-block">Nexa Salud</span>
                <span class="brand-sub d-block">Gestión de Citas Médicas</span>
            </span>
        </div>
        <div>
            <h2 class="h3 fw-bold text-white mb-4">Tu agenda médica, ordenada y segura.</h2>
            <div class="feature"><i class="bi bi-calendar2-check" aria-hidden="true"></i><div><strong class="d-block text-white">Reservas en tiempo real</strong>Horarios validados al momento de confirmar.</div></div>
            <div class="feature"><i class="bi bi-shield-lock" aria-hidden="true"></i><div><strong class="d-block text-white">Acceso por roles</strong>Cada usuario ve solo lo que le corresponde.</div></div>
            <div class="feature"><i class="bi bi-clock-history" aria-hidden="true"></i><div><strong class="d-block text-white">Trazabilidad completa</strong>Cada operación queda registrada en auditoría.</div></div>
        </div>
        <p class="small mb-0" style="color:#94a3b8">© {{ date('Y') }} Nexa Salud</p>
    </aside>
    <main id="main-content" class="auth-panel" tabindex="-1">
        <div class="auth-card">
            <div class="d-flex d-lg-none align-items-center gap-2 mb-4">
                <span class="brand-mark" aria-hidden="true"><i class="bi bi-plus-lg"></i></span>
                <span>
                    <span class="d-block fw-bold text-navy">Nexa Salud</span>
                    <span class="d-block small text-secondary">Gestión de Citas Médicas</span>
                </span>
            </div>
            {{ $slot }}
        </div>
    </main>
</div>
</body>
</html>
