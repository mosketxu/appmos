<?php

namespace App\Http\Livewire;

use App\Models\Entidad;
use Livewire\Component;

class EntidadHistorial extends Component
{
    public $entidad;
    public $ruta='entidad.historial';
    public $histFecha='';
    public $histTexto='';
    public $histImporte='';
    public $histPeriodo='';

    public function mount(Entidad $entidad)
    {
        $this->entidad=$entidad;
        $this->histFecha=now()->toDateString();
    }

    public function render()
    {
        $historico=$this->entidad->historico()->with('user')->get();
        return view('livewire.entidad-historial',compact('historico'));
    }

    /** Comentario suelto en el historial de la entidad (se guarda al momento). */
    public function anadirHistorico()
    {
        if (! ($this->entidad->id ?? null) || ! auth()->user()->can('entidades.editar')) {
            return;
        }
        $this->validate(['histFecha' => 'required|date', 'histTexto' => 'nullable|string|max:2000', 'histImporte' => 'nullable|numeric', 'histPeriodo' => 'nullable']);
        if (trim((string) $this->histTexto) === '' && (string) $this->histImporte === '') {
            $this->addError('histTexto', 'Escribe un comentario o un importe.');
            return;
        }
        \App\Models\EntidadHistorico::create([
            'entidad_id' => $this->entidad->id, 'tipo' => 'comentario', 'fecha' => $this->histFecha,
            'comentario' => trim($this->histTexto) ?: null, 'user_id' => auth()->id(),
            'importe_facturacion' => (string) $this->histImporte === '' ? null : $this->histImporte,
            'periodo_facturacion' => (string) $this->histPeriodo === '' ? null : $this->histPeriodo,
        ]);
        $this->histTexto = '';
        $this->histImporte = '';
        $this->histPeriodo = '';
        $this->histFecha = now()->toDateString();
    }

    public function borrarHistorico(int $id)
    {
        if (auth()->user()->can('entidades.editar')) {
            \App\Models\EntidadHistorico::where('entidad_id', $this->entidad->id)->whereKey($id)->delete();
        }
    }

}
