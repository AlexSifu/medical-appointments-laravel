<#
.SYNOPSIS
  Ejecuta ReservasMedicasWeb_VALIDATE.sql (SOLO LECTURA) y muestra el resultado.
.EXAMPLE
  .\scripts\db-validate.ps1
  .\scripts\db-validate.ps1 -Database ReservasMedicasWeb_Test -Full
#>
param([string]$Server = 'localhost', [string]$Database = '', [switch]$Full)

. "$PSScriptRoot\_common.ps1"
Set-Location $script:Root
if (-not $Database) { $Database = Get-DotEnvValue 'DB_DATABASE' 'ReservasMedicasWeb' }
Assert-SafeDatabaseName $Database

Write-Step "Validando $Database en $Server"
$sqlArgs = @('-S', $Server, '-E', '-d', $Database, '-f', '65001', '-W', '-b', '-i', (Join-Path $script:Root 'database\sql\ReservasMedicasWeb_VALIDATE.sql'))
$output = & sqlcmd @sqlArgs 2>&1
$code = $LASTEXITCODE

if ($Full) { $output | ForEach-Object { Write-Host $_ } }
else { $output | Select-Object -Last 25 | ForEach-Object { Write-Host $_ } }

if ($code -eq 0) { Write-Ok 'VALIDACION: OK' } else { Write-Fail "VALIDACION FALLIDA (sqlcmd exit $code). Use -Full para ver el detalle." }
exit $code
