<?php

namespace App\Http\Livewire\Facturacion;

use App\Models\FacturacionDetalle;
use Livewire\Component;

class FacturaDetallenuevoModal extends Component
{
    public $muestranuevomodal=false;
    public $facturaid=false;
    public $factura=false;
    public $detalleid;
    public $concepto;
    public $orden='0';

    protected function rules(){
        return [
            'concepto'=>'required',
            'orden'=>'nullable',
        ];}

    public function mount($factura){
        $this->facturaid=$factura->id;
        $this->factura=$factura;
    }

    public function render(){
        return view('livewire.facturacion.factura-detallenuevo-modal');
    }

    public function cambianuevomodal(){
        $this->muestranuevomodal= $this->muestranuevomodal==false ? true : false;
    }

    public function cancelarnuevomodal(){
        $this->orden='';
        $this->concepto='';
        $this->muestranuevomodal= false ;
    }

    public function save(){
        $this->orden=$this->orden =='' ? '0' : $this->orden;
        $i=$this->detalleid ? $this->detalleid : '0';
        $this->validate();
        $concepto=FacturacionDetalle::updateOrCreate([
            'id'=>$i
            ],
            [
            'facturacion_id'=>$this->facturaid,
            'orden'=>$this->orden,
            'concepto'=>$this->concepto,
            ]
        );

        $this->orden='';
        $this->concepto='';

        $this->muestranuevomodal=false;
        $notification = array(
            'message' => 'Concepto añadido satisfactoriamente!',
            'alert-type' => 'success'
        );
        return redirect()->route('facturacion.editprefactura',$this->facturaid);

    }
}
