<x-auth-layout title="Iniciar sesión">
    <h1 class="h3 fw-bold mb-1">Bienvenido</h1>
    <p class="text-secondary mb-4">Ingresa con tu usuario o correo electrónico.</p>

    @if (session('status'))
        <x-alert type="success">{{ session('status') }}</x-alert>
    @endif

    <div aria-live="assertive">
        @error('login')
            <x-alert type="danger" id="login-error">{{ $message }}</x-alert>
        @enderror
    </div>

    <form method="POST" action="{{ route('login.attempt') }}" data-submit-once novalidate>
        @csrf
        <div class="mb-3">
            <label for="login" class="form-label">Usuario o correo electrónico</label>
            <div class="input-group">
                <span class="input-group-text" aria-hidden="true"><i class="bi bi-person"></i></span>
                <input type="text" id="login" name="login" value="{{ old('login') }}" class="form-control @error('login') is-invalid @enderror"
                    autocomplete="username" autocapitalize="none" spellcheck="false" maxlength="150" required aria-required="true" autofocus
                    @error('login') aria-invalid="true" aria-describedby="login-error" @enderror>
            </div>
        </div>
        <div class="mb-4">
            <label for="password" class="form-label">Contraseña</label>
            <div class="input-group">
                <span class="input-group-text" aria-hidden="true"><i class="bi bi-lock"></i></span>
                <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror"
                    autocomplete="current-password" maxlength="200" required aria-required="true"
                    @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                <button type="button" class="btn btn-outline-secondary" data-password-toggle="password" aria-pressed="false" aria-label="Mostrar contraseña">
                    <i class="bi bi-eye" aria-hidden="true"></i>
                </button>
            </div>
            @error('password')
                <div id="password-error" class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>
        <button type="submit" class="btn btn-primary w-100 py-2" data-loading-text="Verificando…">
            <i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i> Iniciar sesión
        </button>
    </form>

    <p class="small text-secondary mt-4 mb-0">
        <i class="bi bi-shield-lock me-1" aria-hidden="true"></i>
        Por seguridad, la cuenta se bloquea temporalmente tras varios intentos fallidos. Si olvidaste tu contraseña, comunícate con recepción.
    </p>
</x-auth-layout>
