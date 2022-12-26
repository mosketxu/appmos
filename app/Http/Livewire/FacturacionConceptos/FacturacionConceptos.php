<?php

namespace App\Http\Livewire\FacturacionConceptos;

use App\Models\{FacturacionConcepto,Ciclo, FacturacionConceptodetalle};
use Livewire\Component;

class FacturacionConceptos extends Component
{
    public $entidad;
    public $showEditModal = false;
    public $showDeleteModal = false;
    public FacturacionConcepto $editing;
    public $ruta='entidad.facturacionconceptos';
    public $detalleid;
    public $concepto;
    public $importe;
    public $orden;

    public function rules() {
        return [
            'editing.concepto' => 'required|string',
            'editing.ciclo_id' => 'required|numeric',
            'editing.ciclocorrespondiente' => 'required|numeric',
            // 'editing.id' => 'numeric',
            // 'detalleid' => 'numeric',
            'concepto' => 'required|string',
            'importe' => 'required|numeric',
            'orden' => 'required|numeric',
        ];
    }

    public function mount() {
        $this->editing = $this->makeBlank();
    }

    public function render()
    {
        $ent=$this->entidad;
        $conceptos=FacturacionConcepto::with('detalles')->where('entidad_id',$this->entidad->id)->get();
        $ciclos=Ciclo::get();

        return view('livewire.facturacion-conceptos.facturacion-conceptos',compact('ent','conceptos','ciclos'));
    }

    public function makeBlank(){
        return FacturacionConcepto::make([
            'concepto' => '',
            // 'importe' => '0',
            'ciclo_id' => '',
            'ciclocorrespondiente' => '',
            // 'agrupacion' => '0',
        ]);
    }

    public function create(){
        // dd('sdf');
        if ($this->editing->getKey()) $this->editing = $this->makeBlank();
        $this->showEditModal = true;
    }

    public function edit(FacturacionConceptodetalle $detalle){
        $concepto=FacturacionConcepto::find($detalle->facturacionconcepto_id);
        if ($this->editing->isNot($concepto)){
            $this->editing = $concepto;
            $this->detalleid = $detalle->id;
            $this->concepto = $detalle->concepto;
            $this->importe = $detalle->importe;
            $this->orden = $detalle->orden;
        }
        $this->showEditModal = true;
    }

    public function save(){
        $this->validate();

        $this->editing->entidad_id=$this->entidad->id;
        if($this->editing->id){
            $this->editing->save();
            $detalle=FacturacionConceptodetalle::find($this->detalleid);
            $detalle->concepto=$this->concepto;
            $detalle->importe=$this->importe;
            $detalle->orden=$this->orden;
            $detalle->save();
        }else{
            $f=FacturacionConcepto::create([
                'entidad_id'=>$this->entidad->id,
                'ciclo_id'=>$this->editing->ciclo_id,
                'concepto'=>$this->editing->concepto,
                'ciclocorrespondiente'=>$this->editing->ciclocorrespondiente,
                // 'importe'=>'0',
                ]);
            $d=FacturacionConceptodetalle::create([
                'facturacionconcepto_id'=>$f->id,
                'concepto'=>$this->concepto,
                'importe'=>$this->importe,
                'orden'=>$this->orden,
            ]);
        }
        $this->showEditModal = false;
    }

    public function delete($conceptoId)
    {
        $concepto = FacturacionConcepto::find($conceptoId);
        if ($concepto) {
            $concepto->delete();
            $this->dispatchBrowserEvent('notify', 'El concepto ha sido eliminado!');
        }
    }
}
