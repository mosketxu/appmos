<?php

namespace App\Http\Livewire\Contabilidad;

use Livewire\Component;

/**
 * Pantalla de conciliación de bancos (Contabilidad/Bancos/bancos_conciliacion.py):
 * extractos bancarios de <Cliente>/Input -> <Cliente>/Output/bancos.xlsx para SAGE.
 * Ver PLAN.md en Contabilidad/Bancos para el detalle.
 *
 * De momento solo elige cliente (una carpeta por cliente dentro de Bancos) y
 * muestra lo que tiene pendiente en Input y lo generado en Output. El script
 * todavía pide la partida de cada extracto por consola, así que aún no se lanza
 * desde aquí.
 */
class Bancos extends Component
{
    public string $cliente = '';

    public function mount(): void
    {
        $this->cliente = $this->clientes()[0] ?? '';
    }

    protected function baseDir(): string
    {
        return '/mnt/e/Claude/Contabilidad/Bancos';
    }

    /** Subcarpetas de Bancos que son clientes (todas menos plantillas y las ocultas). */
    protected function clientes(): array
    {
        $dirs = [];
        foreach (glob($this->baseDir().'/*', GLOB_ONLYDIR) ?: [] as $d) {
            $nombre = basename($d);
            if ($nombre === 'plantillas' || str_starts_with($nombre, '.') || str_starts_with($nombre, '_')) {
                continue;
            }
            $dirs[] = $nombre;
        }
        natcasesort($dirs);
        return array_values($dirs);
    }

    /** Ficheros sueltos (no carpetas) de <cliente>/<sub>. */
    protected function ficheros(string $sub): array
    {
        if (! in_array($this->cliente, $this->clientes(), true)) {
            return [];
        }
        $dir = $this->baseDir().'/'.$this->cliente.'/'.$sub;
        $out = [];
        foreach (glob($dir.'/*') ?: [] as $f) {
            if (is_file($f) && ! str_starts_with(basename($f), '~$')) {
                $out[] = basename($f);
            }
        }
        natcasesort($out);
        return array_values($out);
    }

    public function render()
    {
        return view('livewire.contabilidad.bancos', [
            'clientes' => $this->clientes(),
            'pendientes' => $this->ficheros('Input'),
            'generados' => $this->ficheros('Output'),
        ]);
    }
}
