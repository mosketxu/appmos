<?php

namespace App\Http\Livewire\Contabilidad;

use App\Support\ColaTareas;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

/**
 * Limpieza de OneDrive (20-oct-2026, Proc.Mensuales): borra de OneDrive/_Clientes los «_anterior …» que aparta el trabajador y las copias de
 * _copias_libro_iva de más de N días. Un PC trabajador lo hace (tarea pc.limpieza, script Contabilidad/TrabajadorWeb/limpieza_onedrive.py);
 * además miniServ la hace sola el primer día de cada mes. Aquí se lanza a mano: «Simular» solo cuenta, «Limpiar ahora» borra.
 */
class Limpieza extends Component
{
    public int $dias = 7;
    public string $aviso = '';

    protected function colaLista(): bool
    {
        return Schema::hasTable('tareas') && Schema::hasTable('trabajadores');
    }

    public function lanzar(bool $borrar): void
    {
        $this->dias = max(0, min($this->dias, 365));
        if (! auth()->user()->can('contabilidad.procesosmensuales')) {
            return;
        }
        if (! $this->colaLista()) {
            $this->aviso = '⚠️ Falta hacer la migración de la cola de tareas en este servidor.';

            return;
        }
        if (DB::table('tareas')->where('proceso', 'pc.limpieza')->whereIn('estado', ['pendiente', 'en_curso'])->exists()) {
            $this->aviso = 'Ya hay una limpieza en marcha.';

            return;
        }
        ColaTareas::crear('pc.limpieza', ['dias' => $this->dias, 'borrar' => $borrar], ColaTareas::pcElegido() ?: null, auth()->id());
        $this->aviso = $borrar ? 'Pedido a un PC: borrará lo que tenga más de '.$this->dias.' días.' : 'Pedido a un PC: contará lo que borraría, sin borrar nada.';
    }

    public function render()
    {
        $tarea = $this->colaLista()
            ? DB::table('tareas')->where('proceso', 'pc.limpieza')->orderByDesc('id')->first(['id', 'estado', 'parametros', 'resultado', 'terminada_at', 'created_at'])
            : null;
        $resultado = $tarea && $tarea->resultado ? json_decode($tarea->resultado, true) : null;
        $enCurso = $tarea && in_array($tarea->estado, ['pendiente', 'en_curso'], true);

        return view('livewire.contabilidad.limpieza', compact('tarea', 'resultado', 'enCurso'));
    }
}
