<?php

namespace App\Http\Livewire\FacturacionConceptos;

use App\Models\FacturacionConceptodetalle;
use Livewire\Component;

class ConceptoDetalles extends Component{

    public $conceptoid;
    public $color;

    public function mount($concepto,$color){
        $this->conceptoid=$concepto->id;
        $this->color='bg-'.$color.'-50';
    }

    public function render(){

        $detalles=FacturacionConceptodetalle::where('facturacionconcepto_id',$this->conceptoid)->get();
        return view('livewire.facturacion-conceptos.concepto-detalles',compact('detalles'));
    }

}
