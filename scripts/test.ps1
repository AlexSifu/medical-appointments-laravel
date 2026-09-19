<#
.SYNOPSIS
  Ejecuta las pruebas. Unit y Feature no tocan SQL (repositorios simulados); Integration usa
  ReservasMedicasWeb_Test (nunca la BD de desarrollo) y se omite si no está disponible.
.EXAMPLE
  .\scripts\test.ps1                      # todas
  .\scripts\test.ps1 -Suite Unit
  .\scripts\test.ps1 -PrepareTestDatabase # crea/actualiza ReservasMedicasWeb_Test antes (no destructivo)
  .\scripts\test.ps1 -Lint                # además verifica estilo con Pint
#>
param(
    [ValidateSet('All', 'Unit', 'Feature', 'Integration')] [string]$Suite = 'All',
    [string]$Server = 'localhost',
    [switch]$PrepareTestDatabase,
    [switch]$Lint
)

. "$PSScriptRoot\_common.ps1"
Set-Location $script:Root

if ($PrepareTestDatabase) {
    $db = Get-DotEnvValue 'DB_TEST_DATABASE' 'ReservasMedicasWeb_Test'
    Assert-SafeDatabaseName $db
    if ($db -notmatch '_Test$') { throw "La BD de pruebas debe terminar en _Test (actual: $db)" }
    Write-Step "Preparando $db"
    Invoke-Sql -Server $Server -Query "IF DB_ID(N'$db') IS NULL CREATE DATABASE [$db];" | Out-Null
    Invoke-Sql -Server $Server -Database $db -InputFile (Join-Path $script:Root 'database\sql\ReservasMedicasWeb_MASTER.sql') | Out-Null
    Write-Ok 'MASTER aplicado (idempotente)'
}

$exit = 0
if ($Lint) {
    Write-Step 'Pint (estilo)'
    & php vendor\bin\pint --test
    if ($LASTEXITCODE -ne 0) { $exit = 1 }
}

Write-Step "Pruebas: $Suite"
if ($Suite -eq 'All') { & php artisan test } else { & php artisan test --testsuite=$Suite }
if ($LASTEXITCODE -ne 0) { $exit = 1 }
exit $exit
