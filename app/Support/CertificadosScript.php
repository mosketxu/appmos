<?php

namespace App\Support;

/**
 * .ps1 que se descarga desde «Escanear mi PC» (Certificados por caducar): instala una tarea de Windows del propio usuario
 * (sin administrador) que escanea Cert:\CurrentUser\My al iniciar sesión y cada día, y pregunta a la web cada 10 segundos
 * si se ha pulsado «Escanear ahora». Lleva el código personal del usuario. Con -Desinstalar lo quita todo.
 */
class CertificadosScript
{
    public static function generar(string $codigo, string $urlBase): string
    {
        $ps = str_replace(['__URL__', '__CODIGO__'], [rtrim($urlBase, '/'), $codigo], self::PLANTILLA);

        return "\xEF\xBB\xBF".str_replace("\n", "\r\n", $ps);   // UTF-8 con BOM y CRLF: Windows PowerShell 5.1
    }

    private const PLANTILLA = <<<'PS'
# Appmos · escáner de certificados de este PC (se instala para el usuario actual, sin administrador)
# Para quitarlo: powershell -ExecutionPolicy Bypass -File "Instalar-escaner-certificados.ps1" -Desinstalar
param([switch]$Desinstalar)
$ErrorActionPreference = 'Stop'
# La ventana no se cierra sola: se queda hasta que se pulse Intro (así se puede leer el resultado o el error)
trap {
    Write-Host ''
    Write-Host ('ERROR: ' + $_.Exception.Message) -ForegroundColor Red
    Read-Host 'Pulsa Intro para cerrar esta ventana'
    exit 1
}
$dir = Join-Path $env:LOCALAPPDATA 'AppmosCertificados'
$tarea = 'AppmosCertificados'

if ($Desinstalar) {
    Unregister-ScheduledTask -TaskName $tarea -Confirm:$false -ErrorAction SilentlyContinue
    Remove-Item $dir -Recurse -Force -ErrorAction SilentlyContinue
    Write-Host 'Desinstalado: ya no se escanean los certificados de este PC.'
    Read-Host 'Pulsa Intro para cerrar esta ventana'
    exit
}

New-Item -ItemType Directory -Force $dir | Out-Null
$escaner = @'
$url = '__URL__'
$codigo = '__CODIGO__'
$dir = Join-Path $env:LOCALAPPDATA 'AppmosCertificados'
$estado = Join-Path $dir 'ultimo.txt'
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
$h = @{ 'X-Codigo' = $codigo }

function Clave($t, $cn) {
    if ($t -cmatch '\(R: ([A-Z]\d{8})\)') { return 'emp:' + $Matches[1] }
    if ($t -cmatch 'VATES-([A-Z]\d{8})') { return 'emp:' + $Matches[1] }
    if ($cn -cmatch '- ([XYZ]?\d{7,8}[A-Z])\s*$') { return 'per:' + $Matches[1] }
    if ($t -cmatch 'CN=([XYZ]?\d{7,8}[A-Z]) ') { return 'per:' + $Matches[1] }
    return $null
}

function Comprobar {
    try { $p = Invoke-RestMethod -Uri "$url/pendiente" -Headers $h -TimeoutSec 20 } catch { return }
    $toca = (-not (Test-Path $estado)) -or (((Get-Date) - (Get-Item $estado).LastWriteTime).TotalHours -ge 24)
    if (-not ($p.escanear -or $toca)) { return }
    $certs = @()
    foreach ($c in (Get-ChildItem 'Cert:\CurrentUser\My' -ErrorAction SilentlyContinue)) {
        $cn = if ($c.Subject -match 'CN=([^,]+)') { $Matches[1] } else { '' }
        $k = Clave $c.Subject $cn
        if ($k) { $certs += @{ clave = $k; nombre = $cn; alias = [string]$c.FriendlyName; caduca = $c.NotAfter.ToString('yyyy-MM-dd'); huella = $c.Thumbprint } }
    }
    $cuerpo = @{ escaneado = (Get-Date -Format 'yyyy-MM-ddTHH:mm'); equipo = $env:COMPUTERNAME; certs = $certs } | ConvertTo-Json -Depth 4
    try {
        Invoke-RestMethod -Uri "$url/escaneo" -Method Post -Headers $h -ContentType 'application/json; charset=utf-8' -Body ([Text.Encoding]::UTF8.GetBytes($cuerpo)) -TimeoutSec 30 | Out-Null
        Set-Content -Path $estado -Value (Get-Date -Format 'o')
    } catch { }
}

# Cada 5 minutos Windows lanza este script, que pregunta a la web cada 10 segundos durante casi 5 minutos:
# así «Escanear mi PC ahora» se atiende en unos segundos.
$fin = (Get-Date).AddSeconds(285)
do { Comprobar; Start-Sleep -Seconds 10 } while ((Get-Date) -lt $fin)
'@
Set-Content -Path (Join-Path $dir 'escaner.ps1') -Value $escaner -Encoding UTF8

$accion = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument ('-NoProfile -WindowStyle Hidden -ExecutionPolicy Bypass -File "' + (Join-Path $dir 'escaner.ps1') + '"')
$alIniciar = New-ScheduledTaskTrigger -AtLogOn -User "$env:USERDOMAIN\$env:USERNAME"
$cada5 = New-ScheduledTaskTrigger -Once -At (Get-Date) -RepetitionInterval (New-TimeSpan -Minutes 5)
$ajustes = New-ScheduledTaskSettingsSet -StartWhenAvailable -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 6)
try {
    Register-ScheduledTask -TaskName $tarea -Action $accion -Trigger @($alIniciar, $cada5) -Settings $ajustes -Force | Out-Null
} catch {
    Write-Host ('No se ha podido crear la tarea programada: ' + $_.Exception.Message) -ForegroundColor Red
    Read-Host 'Pulsa Intro para cerrar esta ventana'
    exit 1
}
Start-ScheduledTask -TaskName $tarea
Write-Host ''
Write-Host 'LISTO. Este PC escaneará sus certificados al iniciar sesión, cada día y cuando lo pidas desde Appmos.' -ForegroundColor Green
Read-Host 'Pulsa Intro para cerrar esta ventana'
PS;
}
