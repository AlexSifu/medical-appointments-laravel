<#
.SYNOPSIS
  Instalación local NO destructiva: dependencias, .env, BD (se crea solo si no existe), script MASTER
  idempotente, validación, datos demo y build de assets. Nunca ejecuta RESET_DEV ni DROP DATABASE.
.EXAMPLE
  .\scripts\setup.ps1                     # instala todo sobre ReservasMedicasWeb
  .\scripts\setup.ps1 -WithTestDatabase   # además prepara ReservasMedicasWeb_Test (pruebas de integración)
  .\scripts\setup.ps1 -SkipSeed -SkipBuild
#>
param(
    [string]$Server = 'localhost',
    [string]$Database = '',
    [switch]$WithTestDatabase,
    [switch]$SkipDependencies,
    [switch]$SkipSeed,
    [switch]$SkipBuild
)

. "$PSScriptRoot\_common.ps1"
Set-Location $script:Root

if (-not $SkipDependencies) {
    Write-Step 'Dependencias'
    $composer = Resolve-Composer
    if (-not $composer) { throw 'composer no encontrado (PATH ni C:\laragon\bin\composer)' }
    & $composer install --no-interaction
    if ($LASTEXITCODE -ne 0) { throw 'composer install falló' }
    & npm install --no-audit --no-fund
    if ($LASTEXITCODE -ne 0) { throw 'npm install falló' }
}

Write-Step 'Configuración (.env)'
if (-not (Test-Path '.env')) {
    Copy-Item '.env.example' '.env'
    Write-Ok '.env creado desde .env.example: complete DEMO_*_PASSWORD antes de sembrar datos demo.'
} else { Write-Ok '.env existente (no se sobrescribe)' }
if (-not (Get-DotEnvValue 'APP_KEY')) {
    & php artisan key:generate --no-interaction
    Write-Ok 'APP_KEY generada'
}

if (-not $Database) { $Database = Get-DotEnvValue 'DB_DATABASE' 'ReservasMedicasWeb' }
$targets = @($Database)
if ($WithTestDatabase) { $targets += (Get-DotEnvValue 'DB_TEST_DATABASE' 'ReservasMedicasWeb_Test') }

$master = Join-Path $script:Root 'database\sql\ReservasMedicasWeb_MASTER.sql'
$validate = Join-Path $script:Root 'database\sql\ReservasMedicasWeb_VALIDATE.sql'
foreach ($db in $targets) {
    Assert-SafeDatabaseName $db
    Write-Step "Base de datos $db"
    Invoke-Sql -Server $Server -Query "IF DB_ID(N'$db') IS NULL CREATE DATABASE [$db];" | Out-Null
    Write-Ok 'existe (creada solo si faltaba)'
    Invoke-Sql -Server $Server -Database $db -InputFile $master | Out-Null
    Write-Ok 'MASTER aplicado (idempotente)'
    $result = Invoke-Sql -Server $Server -Database $db -InputFile $validate
    $summary = $result | Where-Object { $_ -match 'VALIDACION:' } | Select-Object -Last 1
    Write-Ok "VALIDATE -> $summary"
}

if (-not $SkipSeed) {
    Write-Step 'Datos demo (clinic:seed-demo, idempotente)'
    if (-not (Get-DotEnvValue 'DEMO_SUPERADMIN_PASSWORD')) {
        Write-Warn 'DEMO_*_PASSWORD vacías en .env: se omite. Complete .env y ejecute: php artisan clinic:seed-demo'
    } else {
        & php artisan clinic:seed-demo --no-interaction
        if ($LASTEXITCODE -ne 0) { throw 'clinic:seed-demo falló' }
    }
}

if (-not $SkipBuild) {
    Write-Step 'Assets (Vite)'
    & npm run build
    if ($LASTEXITCODE -ne 0) { throw 'npm run build falló' }
}

Write-Host "`nListo. Inicie con: .\scripts\start.ps1  (http://127.0.0.1:8000)" -ForegroundColor Green
