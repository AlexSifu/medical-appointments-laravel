<#
.SYNOPSIS
  Diagnóstico del entorno (SOLO LECTURA). No instala ni modifica nada.
.EXAMPLE
  .\scripts\check-environment.ps1
  .\scripts\check-environment.ps1 -Server "localhost\SQLEXPRESS"
#>
param([string]$Server = 'localhost')

. "$PSScriptRoot\_common.ps1"
$ErrorActionPreference = 'Continue'
$problems = 0
Set-Location $script:Root

Write-Step 'PHP'
if (Test-Command php) {
    $version = (& php -r 'echo PHP_VERSION;')
    if ([version]$version -ge [version]'8.2') { Write-Ok "PHP $version" } else { Write-Fail "PHP $version (se requiere >= 8.2)"; $problems++ }
    $modules = & php -m
    foreach ($ext in 'pdo_sqlsrv', 'sqlsrv', 'mbstring', 'openssl', 'intl', 'fileinfo') {
        if ($modules -contains $ext) { Write-Ok "extensión $ext" }
        elseif ($ext -eq 'pdo_sqlsrv') { Write-Fail 'extensión pdo_sqlsrv no cargada (obligatoria)'; $problems++ }
        else { Write-Warn "extensión $ext no cargada" }
    }
} else { Write-Fail 'php no está en PATH'; $problems++ }

Write-Step 'Composer / Node'
$composer = Resolve-Composer
if ($composer) { Write-Ok "composer $((& $composer --version 2>$null | Select-Object -First 1))" } else { Write-Fail 'composer no encontrado (PATH ni Laragon)'; $problems++ }
foreach ($tool in 'node', 'npm') {
    if (Test-Command $tool) { Write-Ok "$tool $((& $tool --version 2>$null | Select-Object -First 1))" } else { Write-Fail "$tool no está en PATH"; $problems++ }
}

Write-Step 'SQL Server'
if (Test-Command sqlcmd) {
    try {
        $v = Invoke-Sql -Server $Server -Query "SELECT CAST(SERVERPROPERTY('ProductVersion') AS varchar(30)) + ' ' + CAST(SERVERPROPERTY('Edition') AS varchar(60))" -Quiet
        Write-Ok "Conexión a $Server (Windows): $($v | Select-Object -First 1)"
        foreach ($db in (Get-DotEnvValue 'DB_DATABASE' 'ReservasMedicasWeb'), (Get-DotEnvValue 'DB_TEST_DATABASE' 'ReservasMedicasWeb_Test'), 'ReservasMedicasDB') {
            $exists = ("" + (Invoke-Sql -Server $Server -Query "SELECT CASE WHEN DB_ID(N'$db') IS NULL THEN 0 ELSE 1 END" -Quiet | Select-Object -First 1)).Trim()
            $note = if ($db -eq 'ReservasMedicasDB') { ' (legacy VB.NET, no se modifica)' } else { '' }
            if ($exists -eq '1') { Write-Ok "BD $db existe$note" } else { Write-Warn "BD $db no existe$note" }
        }
    } catch { Write-Fail "No se pudo conectar a SQL Server ($Server): $_"; $problems++ }
} else { Write-Fail 'sqlcmd no está en PATH'; $problems++ }

Write-Step 'Proyecto'
if (Test-Path '.env') {
    Write-Ok '.env presente'
    if (Get-DotEnvValue 'APP_KEY') { Write-Ok 'APP_KEY definida' } else { Write-Warn 'APP_KEY vacía: php artisan key:generate' }
    foreach ($k in 'DEMO_SUPERADMIN_PASSWORD', 'DEMO_PATIENT_PASSWORD') {
        if (Get-DotEnvValue $k) { Write-Ok "$k definida (valor oculto)" } else { Write-Warn "$k vacía: clinic:seed-demo la necesita" }
    }
} else { Write-Warn '.env no existe: copie .env.example (setup.ps1 lo hace)' }
if (Test-Path 'vendor\autoload.php') { Write-Ok 'dependencias composer instaladas' } else { Write-Warn 'falta vendor/: composer install' }
if (Test-Path 'node_modules') { Write-Ok 'dependencias npm instaladas' } else { Write-Warn 'falta node_modules/: npm install' }
if (Test-Path 'public\build\manifest.json') { Write-Ok 'assets compilados (public/build)' } else { Write-Warn 'assets sin compilar: npm run build' }

Write-Host ''
if ($problems -eq 0) { Write-Host 'Entorno listo.' -ForegroundColor Green; exit 0 }
Write-Host "Se encontraron $problems problema(s) bloqueante(s)." -ForegroundColor Red
exit 1
