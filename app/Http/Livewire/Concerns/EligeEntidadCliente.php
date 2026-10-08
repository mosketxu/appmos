<?php

namespace App\Http\Livewire\Concerns;

use App\Support\ClientesEntidad;

/**
 * Desplegable de cliente de Facturas OCR y Bancos con todas las entidades activas del usuario (8-oct-2026): si la elegida no tiene carpeta,
 * un modal propone el nombre y la carpeta anual de OneDrive (editables) y la crea. El componente aporta clientes(), baseDir() y alElegirCliente().
 */
trait EligeEntidadCliente
{
    public string $clienteOk = '';
    public bool $modalNuevo = false;
    public int $nuevoEid = 0;
    public string $nuevoNombre = '';
    public string $nuevoAnual = '';
    public string $nuevoEntidad = '';
    public bool $nuevoDetectada = false;
    public string $nuevoError = '';

    protected function opcionesClientes(): array
    {
        return ClientesEntidad::opciones($this->clientes(), $this->baseDir());
    }

    /** La «OneDrive» donde buscar/crear la carpeta anual: la copia de trabajo del VPS o la real de este PC. */
    protected function raizOneDrive(): ?string
    {
        if (config('contabilidad.facturasocr_onedrive')) {
            return rtrim((string) config('contabilidad.facturasocr_onedrive'), '/');
        }
        foreach (['e', 'f', 'd', 'c', 'g'] as $u) {
            if (is_dir("/mnt/{$u}/OneDrive")) {
                return "/mnt/{$u}/OneDrive";
            }
        }

        return null;
    }

    /** Llamar al principio de updatedCliente(): true si era una entidad sin carpeta (se abre el modal y no se cambia de cliente). */
    protected function interceptaNuevo(): bool
    {
        if (! str_starts_with($this->cliente, 'e:')) {
            $this->clienteOk = $this->cliente;

            return false;
        }
        $eid = (int) substr($this->cliente, 2);
        $this->cliente = $this->clienteOk;
        try {
            $p = ClientesEntidad::proponer($eid, $this->raizOneDrive());
        } catch (\Throwable $e) {
            return true;
        }
        $this->nuevoEid = $eid;
        $this->nuevoNombre = $p['nombre'];
        $this->nuevoAnual = $p['anual'];
        $this->nuevoEntidad = $p['entidad'].($p['nif'] ? ' · '.$p['nif'] : '');
        $this->nuevoDetectada = $p['detectada'];
        $this->nuevoError = '';
        $this->modalNuevo = true;

        return true;
    }

    public function cancelarNuevo(): void
    {
        $this->modalNuevo = false;
    }

    public function crearClienteNuevo(): void
    {
        $nombre = trim($this->nuevoNombre);
        $anual = trim($this->nuevoAnual);
        if (! ClientesEntidad::nombreValido($nombre)) {
            $this->nuevoError = 'El nombre del cliente solo puede llevar letras, números, espacios, punto y guion.';

            return;
        }
        if (! ClientesEntidad::anualValida($anual) || ! str_contains($anual, '{AAAA}')) {
            $this->nuevoError = 'La carpeta anual debe llevar {AAAA} donde va el año (p. ej. «Fashion {AAAA}»).';

            return;
        }
        $cfg = json_decode((string) @file_get_contents($this->baseDir().'/'.$nombre.'/cliente.json'), true);
        if (is_dir($this->baseDir().'/'.$nombre) && (int) ($cfg['entidad_id'] ?? 0) !== $this->nuevoEid) {
            $this->nuevoError = "Ya hay un cliente llamado «{$nombre}» de otra entidad: cambia el nombre.";

            return;
        }
        try {
            $hechos = ClientesEntidad::crear($this->nuevoEid, $nombre, $anual, $this->raizOneDrive());
        } catch (\Throwable $e) {
            $this->nuevoError = 'No se pudo crear: '.$e->getMessage();

            return;
        }
        if (! in_array($nombre, $this->clientes(), true)) {
            $this->nuevoError = 'No se pudo crear la carpeta del cliente (¿permisos?).';

            return;
        }
        $this->modalNuevo = false;
        $this->cliente = $this->clienteOk = $nombre;
        $this->alElegirCliente();
    }
}
