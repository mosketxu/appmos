<?php

namespace App\Http\Livewire;

use App\Models\{Entidad, Facturacion, MetodoPago};
use Livewire\Component;

class Factura extends Component
{

    public $factura;

    protected function rules(){
        return [
            'factura.id'=>'nullable',
            'factura.numfactura'=>'string|nullable',
            'factura.invento'=>'string|nullable',
            'factura.entidad_id'=>'required',
            'factura.fechafactura'=>'date|nullable',
            'factura.fechavencimiento'=>'date|nullable',
            'factura.metodopago_id'=>'numeric|nullable',
            'factura.refcliente'=>'nullable',
            'factura.mail'=>'nullable',
            'factura.enviar'=>'nullable',
            'factura.enviada'=>'nullable',
            'factura.pagada'=>'nullable',
            'factura.facturable'=>'nullable',
            'factura.asiento'=>'numeric|nullable',
            'factura.fechaasiento'=>'date|nullable',
            'factura.observaciones'=>'nullable',
            'factura.notas'=>'nullable',
        ];
    }

    public function mount(Facturacion $facturacion)
    {
        $this->factura=$facturacion;
        $this->factura->enviar=1;
        $this->factura->enviada=0;
        $this->factura->pagada=0;
        $this->factura->facturable=1;
    }

    public function render()
    {
        $factura=$this->factura;

        $entidades=Entidad::where('estado','1')->orderBy('entidad')->get();
        $pagos=MetodoPago::all();
        // dd($entidades);
        return view('livewire.factura',compact('entidades','pagos'));
    }

    public function save()
    {
        $this->validate();

        if($this->factura->id){
            $i=$this->factura->id;
            $mensaje="Factura actualizada satisfactoriamente";
        }else{
            $i=$this->factura->id;
            $mensaje="Factura creada satisfactoriamente";
        }

        $fac=Facturacion::updateOrCreate([
            'id'=>$i
            ],
            [
                'numfactura'=>$this->factura->numfactura,
                'entidad_id'=>$this->factura->entidad_id,
                'fechafactura'=>$this->factura->fechafactura,
                'fechavencimiento'=>$this->factura->fechavencimiento,
                'metodopago_id'=>$this->factura->metodopago_id,
                'refcliente'=>$this->factura->refcliente,
                'mail'=>$this->factura->mail,
                'enviar'=>$this->factura->enviar,
                'enviada'=>$this->factura->enviada,
                'pagada'=>$this->factura->pagada,
                'facturable'=>$this->factura->facturable,
                'asiento'=>$this->factura->asiento,
                'fechaasiento'=>$this->factura->fechaasiento,
                'observaciones'=>$this->factura->observaciones,
                'notas'=>$this->factura->notas,
            ]
        );

        if(!$i){
            $this->factura->id=$fac->id;
        }
        $this->emitSelf('notify-saved');
    }
}
