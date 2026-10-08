<?php

namespace App\Http\Livewire\Concerns;

use App\Support\ClientesEntidad;
use App\Support\ColaTareas;

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
    public string $nuevoCarpeta = '';
    public bool $explorando = false;
    public string $expRuta = '';
    public string $expError = '';
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

    /** Cliente con el que se abre la pantalla: el último que se eligió (en Facturas OCR o Bancos) si sigue en la lista; si no, el primero. */
    protected function clienteInicial(array $lista): string
    {
        $c = (string) session('contabilidad_cliente', '');

        return in_array($c, $lista, true) ? $c : ($lista[0] ?? '');
    }

    /** Llamar al principio de updatedCliente(): true si era una entidad sin carpeta (se abre el modal y no se cambia de cliente). */
    protected function interceptaNuevo(): bool
    {
        if (! str_starts_with($this->cliente, 'e:')) {
            $this->clienteOk = $this->cliente;
            session(['contabilidad_cliente' => $this->cliente]);   // al refrescar o volver a esta pantalla se sigue en el mismo cliente

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
        $this->nuevoCarpeta = $p['carpeta'];
        $this->explorando = false;
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
        $carpeta = trim($this->nuevoCarpeta);
        if (! ClientesEntidad::nombreValido($nombre)) {
            $this->nuevoError = 'El nombre del cliente solo puede llevar letras, números, espacios, punto y guion.';

            return;
        }
        if (! ClientesEntidad::carpetaValida($carpeta)) {
            $this->nuevoError = 'Elige la carpeta de las facturas con «Examinar…».';

            return;
        }
        $cfg = json_decode((string) @file_get_contents($this->baseDir().'/'.$nombre.'/cliente.json'), true);
        if (is_dir($this->baseDir().'/'.$nombre) && (int) ($cfg['entidad_id'] ?? 0) !== $this->nuevoEid) {
            $this->nuevoError = "Ya hay un cliente llamado «{$nombre}» de otra entidad: cambia el nombre.";

            return;
        }
        try {
            $hechos = ClientesEntidad::crear($this->nuevoEid, $nombre, $carpeta, $this->raizOneDrive());
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
        session(['contabilidad_cliente' => $nombre]);
        $this->alElegirCliente();
    }

    // ------------------------------------------------------------ explorador de carpetas de OneDrive (el árbol lo lee un PC)

    /** Token de la ventana de Windows pedida a un PC (selector de carpetas nativo); mientras no está vacío, el modal sondea el resultado. */
    public string $ventanaToken = '';

    /** «Examinar…»: un PC abre el selector de carpetas de Windows (el del Explorador) y devuelve la carpeta elegida. */
    public function elegirConWindows(): void
    {
        $this->expError = '';
        $this->nuevoError = '';
        $this->explorando = false;
        $this->ventanaToken = bin2hex(random_bytes(6));
        ColaTareas::crear('pc.elegir_carpeta', ['token' => $this->ventanaToken], ColaTareas::pcElegido() ?: null, auth()->id());
    }

    /** wire:poll mientras se espera: recoge la carpeta elegida en la ventana de Windows. */
    public function revisarVentana(): void
    {
        if ($this->ventanaToken === '') {
            return;
        }
        $e = ColaTareas::estado('onedrive.carpeta_elegida');
        if (! is_array($e) || ($e['token'] ?? '') !== $this->ventanaToken) {
            return;
        }
        $this->ventanaToken = '';
        if (! empty($e['cancelada'])) {
            return;
        }
        if (! empty($e['error'])) {
            $this->nuevoError = (string) $e['error'];

            return;
        }
        $c = ClientesEntidad::aCarpeta((string) ($e['ruta'] ?? ''));
        if ($c === null) {
            $this->nuevoError = 'Elige una carpeta dentro de _Clientes › año › cliente (donde están las facturas recibidas).';

            return;
        }
        $this->nuevoCarpeta = $c;
    }

    public function cancelarVentana(): void
    {
        $this->ventanaToken = '';
    }

    public function abrirExplorador(): void
    {
        $this->expError = '';
        if (! ClientesEntidad::arbol()) {
            ClientesEntidad::pedirArbol();
        }
        $ruta = '_Clientes/'.date('Y').'/'.str_replace('{AAAA}', date('Y'), $this->nuevoCarpeta);
        $dirs = ClientesEntidad::arbol()['dirs'] ?? [];
        while ($ruta !== '' && $dirs && ! in_array($ruta, $dirs, true)) {   // la más cercana que exista
            $ruta = str_contains($ruta, '/') ? substr($ruta, 0, strrpos($ruta, '/')) : '';
        }
        $this->expRuta = $ruta !== '' ? $ruta : '_Clientes/'.date('Y');
        $this->explorando = true;
    }

    public function actualizarArbol(): void
    {
        ClientesEntidad::pedirArbol();
    }

    public function cerrarExplorador(): void
    {
        $this->explorando = false;
    }

    public function irACarpeta(string $ruta): void
    {
        $dirs = ClientesEntidad::arbol()['dirs'] ?? [];
        if ($ruta === '_Clientes' || in_array($ruta, $dirs, true)) {
            $this->expRuta = $ruta;
        }
    }

    public function elegirCarpetaExplorada(): void
    {
        $c = ClientesEntidad::aCarpeta($this->expRuta);
        if ($c === null) {
            $this->expError = 'Entra en la carpeta del cliente (dentro del año) y elige la carpeta donde están las facturas recibidas.';

            return;
        }
        $this->nuevoCarpeta = $c;
        $this->explorando = false;
    }

    /** Datos para pintar el explorador. */
    public function datosExplorador(): array
    {
        $a = ClientesEntidad::arbol();
        $partes = $this->expRuta === '' ? [] : explode('/', $this->expRuta);
        $migas = [];
        $acum = '';
        foreach ($partes as $p) {
            $acum = $acum === '' ? $p : $acum.'/'.$p;
            $migas[] = ['t' => $p, 'ruta' => $acum];
        }

        return [
            'hay' => (bool) $a, 'fecha' => $a['fecha'] ?? '', 'pc' => $a['pc'] ?? '', 'enCurso' => ClientesEntidad::arbolEnCurso(),
            'migas' => $migas, 'hijas' => $a ? ClientesEntidad::hijas($a['dirs'], $this->expRuta) : [],
            'padre' => count($partes) > 1 ? implode('/', array_slice($partes, 0, -1)) : null,
        ];
    }
}
