# Funciones compartidas por los scripts de Nexa Salud.
$ErrorActionPreference = 'Stop'
$script:Root = Split-Path -Parent $PSScriptRoot

function Write-Step([string]$Text) { Write-Host "`n==> $Text" -ForegroundColor Cyan }
function Write-Ok([string]$Text) { Write-Host "  [OK]    $Text" -ForegroundColor Green }
function Write-Warn([string]$Text) { Write-Host "  [AVISO] $Text" -ForegroundColor Yellow }
function Write-Fail([string]$Text) { Write-Host "  [ERROR] $Text" -ForegroundColor Red }

function Test-Command([string]$Name) { return [bool](Get-Command $Name -ErrorAction SilentlyContinue) }

# Composer: PATH o la instalación de Laragon (C:\laragon\bin\composer).
function Resolve-Composer {
    if (Test-Command composer) { return 'composer' }
    foreach ($candidate in 'C:\laragon\bin\composer\composer.bat', "$env:APPDATA\ComposerSetup\bin\composer.bat") {
        if (Test-Path $candidate) { return $candidate }
    }
    return $null
}

# Lee una clave de .env sin mostrar su valor.
function Get-DotEnvValue([string]$Key, [string]$Default = '') {
    $envFile = Join-Path $script:Root '.env'
    if (-not (Test-Path $envFile)) { return $Default }
    foreach ($line in Get-Content $envFile -Encoding UTF8) {
        if ($line -match "^\s*$([regex]::Escape($Key))\s*=\s*(.*)$") {
            $value = $Matches[1].Trim().Trim('"').Trim("'")
            if ($value) { return $value }
            return $Default
        }
    }
    return $Default
}

# sqlcmd con autenticación de Windows (-E); lanza excepción si falla (-b).
function Invoke-Sql {
    param(
        [Parameter(Mandatory)] [string]$Server,
        [string]$Database = 'master',
        [string]$Query,
        [string]$InputFile,
        [switch]$Quiet
    )
    $sqlArgs = @('-S', $Server, '-E', '-d', $Database, '-b', '-f', '65001', '-W')
    if ($InputFile) { $sqlArgs += @('-i', $InputFile) } else { $sqlArgs += @('-Q', "SET NOCOUNT ON; $Query") }
    if ($Quiet) { $sqlArgs += @('-h', '-1') }
    $output = & sqlcmd @sqlArgs 2>&1
    if ($LASTEXITCODE -ne 0) { throw "sqlcmd falló ($LASTEXITCODE): $($output -join [Environment]::NewLine)" }
    return $output
}

function Assert-SafeDatabaseName([string]$Database) {
    if ($Database -notmatch '^[A-Za-z][A-Za-z0-9_]{0,100}$') { throw "Nombre de base de datos no válido: $Database" }
    if ($Database -eq 'ReservasMedicasDB') { throw 'ReservasMedicasDB es la base legacy (VB.NET): estos scripts nunca la modifican.' }
}
