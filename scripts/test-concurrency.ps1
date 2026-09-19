<#
.SYNOPSIS
  Concurrencia real contra ReservasMedicasWeb_Test: 6 procesos PHP independientes intentan reservar el
  MISMO horario al mismo instante; SQL Server debe aceptar exactamente una reserva. También verifica
  idempotencia. La reserva ganadora se cancela al final (prueba repetible). No toca la BD de desarrollo.
.EXAMPLE
  .\scripts\test-concurrency.ps1
  .\scripts\test-concurrency.ps1 -Repeat 5
#>
param([int]$Repeat = 1)

. "$PSScriptRoot\_common.ps1"
Set-Location $script:Root

$failed = 0
for ($i = 1; $i -le $Repeat; $i++) {
    Write-Step "Ronda $i de $Repeat"
    & php artisan test --testsuite=Integration --filter='ReservationConcurrencyTest|test_same_idempotency_key'
    if ($LASTEXITCODE -ne 0) { $failed++ }
}

if ($failed -eq 0) { Write-Ok "Concurrencia e idempotencia correctas en $Repeat ronda(s)."; exit 0 }
Write-Fail "$failed ronda(s) fallida(s)."
exit 1
