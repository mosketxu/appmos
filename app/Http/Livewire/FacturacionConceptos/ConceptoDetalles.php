<?php

namespace App\Http\Livewire\FacturacionConceptos;

use App\Models\FacturacionConcepto;
use App\Models\FacturacionConceptodetalle;
use Livewire\Component;

class ConceptoDetalles extends Component{

    public $conceptoid;
    public $color;
    // public $facturacionconcepto_id;

    public function mount($concepto,$color){
        $this->conceptoid=$concepto->id;
        $this->color='bg-'.$color.'-50';
    }

    public function render(){
        $detalles=FacturacionConceptodetalle::where('facturacionconcepto_id',$this->conceptoid)->get();
        return view('livewire.facturacion-conceptos.concepto-detalles',compact('detalles'));
    }

    public function delete($conceptoid)
    {
        $borrar = FacturacionConcepto::find($conceptoid);

        if ($borrar) {
            $borrar->delete();
            // Facturacion::actualizaimportes($this->detalle->facturacion_id);
            $this->dispatchBrowserEvent('notify', 'Concepto eliminado!');
        }

    }

}
