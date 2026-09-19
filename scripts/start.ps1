<#
.SYNOPSIS
  Inicia la aplicación con php artisan serve (http://127.0.0.1:8000). Compila assets si faltan.
.EXAMPLE
  .\scripts\start.ps1
  .\scripts\start.ps1 -Port 8080
#>
param([string]$HostName = '127.0.0.1', [int]$Port = 8000)

. "$PSScriptRoot\_common.ps1"
Set-Location $script:Root

if (-not (Test-Path '.env')) { throw 'Falta .env: ejecute primero .\scripts\setup.ps1' }
if (-not (Test-Path 'public\build\manifest.json')) {
    Write-Step 'Compilando assets (no existe public/build)'
    & npm run build
    if ($LASTEXITCODE -ne 0) { throw 'npm run build falló' }
}

Write-Step "Nexa Salud en http://${HostName}:$Port  (Ctrl+C para detener)"
& php artisan serve --host=$HostName --port=$Port
