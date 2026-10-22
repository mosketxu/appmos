<?php

namespace App\Support;

/**
 * .ps1 que se descarga desde «Escanear mi PC» (Certificados por caducar). Se instala una sola vez, para el usuario de Windows
 * y sin administrador: deja el escáner en %LOCALAPPDATA% y registra el protocolo appmos-certificados:// (HKCU). Nada corre
 * en segundo plano: cuando se pulsa «Escanear mi PC ahora» en la web, el navegador abre ese protocolo, se lanza el escáner
 * una vez, lee Cert:\CurrentUser\My y sube el resultado con el código personal del usuario. Con -Desinstalar se quita todo.
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
$clave = 'HKCU:\Software\Classes\appmos-certificados'

# Versiones anteriores dejaban una tarea programada: fuera
Unregister-ScheduledTask -TaskName 'AppmosCertificados' -Confirm:$false -ErrorAction SilentlyContinue

if ($Desinstalar) {
    Remove-Item $clave -Recurse -Force -ErrorAction SilentlyContinue
    Remove-Item $dir -Recurse -Force -ErrorAction SilentlyContinue
    Write-Host 'Desinstalado: ya no se escanean los certificados de este PC.'
    Read-Host 'Pulsa Intro para cerrar esta ventana'
    exit
}

New-Item -ItemType Directory -Force $dir | Out-Null
$escaner = @'
$url = '__URL__'
$codigo = '__CODIGO__'
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

function Clave($t, $cn) {
    if ($t -cmatch '\(R: ([A-Z]\d{8})\)') { return 'emp:' + $Matches[1] }
    if ($t -cmatch 'VATES-([A-Z]\d{8})') { return 'emp:' + $Matches[1] }
    if ($cn -cmatch '- ([XYZ]?\d{7,8}[A-Z])\s*$') { return 'per:' + $Matches[1] }
    if ($t -cmatch 'CN=([XYZ]?\d{7,8}[A-Z]) ') { return 'per:' + $Matches[1] }
    return $null
}
$certs = @()
foreach ($c in (Get-ChildItem 'Cert:\CurrentUser\My' -ErrorAction SilentlyContinue)) {
    $cn = if ($c.Subject -match 'CN=([^,]+)') { $Matches[1] } else { '' }
    $k = Clave $c.Subject $cn
    if ($k) { $certs += @{ clave = $k; nombre = $cn; alias = [string]$c.FriendlyName; caduca = $c.NotAfter.ToString('yyyy-MM-dd'); huella = $c.Thumbprint } }
}
$cuerpo = @{ escaneado = (Get-Date -Format 'yyyy-MM-ddTHH:mm'); equipo = $env:COMPUTERNAME; certs = $certs } | ConvertTo-Json -Depth 4
Invoke-RestMethod -Uri "$url/escaneo" -Method Post -Headers @{ 'X-Codigo' = $codigo } -ContentType 'application/json; charset=utf-8' -Body ([Text.Encoding]::UTF8.GetBytes($cuerpo)) -TimeoutSec 30 | Out-Null
'@
$ruta = Join-Path $dir 'escaner.ps1'
Set-Content -Path $ruta -Value $escaner -Encoding UTF8

# Protocolo appmos-certificados:// (solo para este usuario): lo abre el botón «Escanear mi PC ahora» de la web
New-Item -Path $clave -Force | Out-Null
Set-ItemProperty -Path $clave -Name '(Default)' -Value 'URL:Appmos certificados'
New-ItemProperty -Path $clave -Name 'URL Protocol' -Value '' -PropertyType String -Force | Out-Null
New-Item -Path "$clave\shell\open\command" -Force | Out-Null
Set-ItemProperty -Path "$clave\shell\open\command" -Name '(Default)' -Value ('powershell.exe -NoProfile -WindowStyle Hidden -ExecutionPolicy Bypass -File "' + $ruta + '"')

# Primer escaneo ahora mismo
Write-Host 'Escaneando tus certificados...'
& powershell.exe -NoProfile -ExecutionPolicy Bypass -File $ruta
Write-Host ''
Write-Host 'LISTO. A partir de ahora, cada vez que pulses «Escanear mi PC ahora» en Appmos se escaneará este PC. No queda nada en marcha en segundo plano.' -ForegroundColor Green
Read-Host 'Pulsa Intro para cerrar esta ventana'
PS;
}
