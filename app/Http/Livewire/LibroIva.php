<?php

namespace App\Http\Livewire;

use App\Support\Impuestos as Imp;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Libro de IVA (303) maquetado — PROPUESTA DE PANTALLA (9-oct-2026). Aún no ejecuta nada: muestra el flujo
 * (empresa → periodo → carpeta → ficheros → generar) con el resultado real de Alex Arregui 3T-2026 como ejemplo.
 * Motor ya hecho: Contabilidad/Impuestos/M303/libro_iva.py (ver PLAN.md). Pendiente de conectar: campo de carpeta por entidad,
 * tarea `pc.libro_iva` del trabajador y listado de carpetas (`pc.listar_carpetas`).
 */
class LibroIva extends Component
{
    public string $entidadId = '';
    public string $ejercicio = '';
    public string $periodo = '3T';
    public string $carpeta = '';
    public string $aviso = '';

    public function mount(): void
    {
        $this->ejercicio = (string) date('Y');
        $this->entidadId = (string) (collect($this->empresas())->keys()->first() ?? '');
        $this->carpeta = $this->carpetaDe($this->entidadId);
    }

    public function updatedEntidadId(): void
    {
        $this->carpeta = $this->carpetaDe($this->entidadId);
        $this->aviso = '';
    }

    /** Empresas con 303 visibles para el usuario (las mismas reglas que la pestaña Impuestos). */
    public function empresas(): array
    {
        $q = DB::table('entidad_impuestos as ei')->join('entidades as e', 'e.id', '=', 'ei.entidad_id')
            ->join('impuesto_modelos as m', 'm.id', '=', 'ei.modelo_id')->where('m.codigo', '303')->where('e.estado', 1)
            ->select('e.id', 'e.entidad')->distinct()->orderBy('e.entidad');
        Imp::soloVisibles($q, null, false);
        return $q->pluck('e.entidad', 'e.id')->all();
    }

    /** Propuesta: la carpeta se guardará por entidad. De momento solo se conoce la de la primera prueba. */
    protected function carpetaDe(string $id): string
    {
        $nombre = $this->empresas()[$id] ?? '';
        return stripos($nombre, 'arregui') !== false ? 'E:\\OneDrive\\_RUR_Marta_Alex\\2026 RMA\\_Alex 2026\\IVA' : '';
    }

    public function generar(): void
    {
        $this->aviso = 'Propuesta de pantalla: «Generar» todavía no está conectado al PC trabajador. Dime si el diseño te vale y lo conecto.';
    }

    public function render()
    {
        return view('livewire.libro-iva', ['empresas' => $this->empresas()]);
    }
}
