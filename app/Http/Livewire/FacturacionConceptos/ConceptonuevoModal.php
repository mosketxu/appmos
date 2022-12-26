<?php

namespace App\Http\Livewire\FacturacionConceptos;

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

    public function render()
    {
        return view('livewire.facturacion-conceptos.conceptonuevo-modal');
    }

    public function cambianuevomodal(){
        $this->muestranuevomodal= $this->muestranuevomodal==false ? true : false;
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

        // dd($concepto);
        $mensaje="Concepto creado con éxito";
        $this->dispatchBrowserEvent('notify', $mensaje);
    }
}
