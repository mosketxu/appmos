<?php

namespace App\Http\Livewire\FacturacionConceptos;

use App\Models\Ciclo;
use App\Models\Entidad;
use App\Models\FacturacionConcepto;
use Livewire\Component;

class ConceptonuevoModal extends Component
{
    public $muestranuevomodal=false;
    public $conceptoid;
    public $entidadid;
    public $entidad;
    public $ciclofacturacionid;
    public $agrupacion;
    public $ciclocorrespondiente;

    public function mount($entidad)
    {
        $this->entidadid=$entidad->id;
        $this->entidad=$entidad->entidad;
    }

    protected function rules(){
    return [
        'entidadid'=>'required',
        'ciclofacturacionid'=>'required',
        'agrupacion'=>'required',
        'ciclocorrespondiente'=>'required',
    ];}

    public function render(){

        $ciclosfact=Ciclo::get();
        return view('livewire.facturacion-conceptos.conceptonuevo-modal',compact('ciclosfact'));
    }

    public function cambianuevomodal(){
        $this->muestranuevomodal= $this->muestranuevomodal==false ? true : false;
    }

    public function cancelarnuevomodal(){
        $this->ciclo_id='';
        $this->concepto='';
        $this->ciclocorrespondiente='';
        $this->muestranuevomodal= false ;
    }

    public function save()
    {
        $i=$this->conceptoid ? $this->conceptoid : '0';
        $agrupacion=FacturacionConcepto::updateOrCreate([
            'id'=>$i
            ],
            [
            'entidad_id'=>$this->entidadid,
            'ciclo_id'=>$this->ciclofacturacionid,
            'concepto'=>$this->agrupacion,
            'ciclocorrespondiente'=>$this->ciclocorrespondiente,
            ]
        );

        $this->ciclo_id='';
        $this->concepto='';
        $this->ciclocorrespondiente='';

        $e=Entidad::find($this->entidadid);
        $this->muestranuevomodal=false;
        $notification = array(
            'message' => 'Concepto añadido satisfactoriamente!',
            'alert-type' => 'success'
        );
        return redirect()->route('facturacionconcepto.entidad',$e)->with($notification);
        // route('facturacionconcepto.entidad',$entidad)
    }
}
