<?php

namespace App\Http\Livewire;

use App\Support\ColaTareas;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

/**
 * Icono de los PCs de trabajo en la barra de menú, junto al usuario (todos los usuarios): ¿hay algún PC listo para
 * ejecutar lo que se pida desde la web?, ¿cuántas tareas hay en cola o en curso? Se refresca solo cada 30 s.
 */
class TrabajadoresEstado extends Component
{
    public function render()
    {
        // en un PC no hay cola propia (los datos buenos están en la web)
        if (config('contabilidad.todo_url') || ! Schema::hasTable('trabajadores') || ! Schema::hasTable('tareas')) {
            return view('livewire.trabajadores-estado', ['e' => null]);
        }
        $limite = now()->subSeconds(ColaTareas::LATIDO_MAX)->toDateTimeString();
        $pcs = DB::table('trabajadores')->where('activo', true)->orderBy('nombre')->get()
            ->map(fn ($w) => ['nombre' => $w->nombre, 'en_linea' => (bool) ($w->ultimo_latido && $w->ultimo_latido >= $limite)])->all();
        $q = DB::table('tareas')->whereIn('estado', ['pendiente', 'en_curso'])->where('proceso', '!=', 'claude.todo');
        $mias = auth()->id() ? (clone $q)->where('user_id', auth()->id())->count() : 0;

        return view('livewire.trabajadores-estado', ['e' => [
            'pcs' => $pcs, 'enlinea' => count(array_filter($pcs, fn ($p) => $p['en_linea'])),
            'activas' => (clone $q)->count(), 'mias' => $mias,
        ]]);
    }
}
