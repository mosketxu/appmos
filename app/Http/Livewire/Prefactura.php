<?php

namespace App\Http\Livewire;

// use App\Actions\MonthQuarterAction;

use App\Actions\FacturaConceptoStoreAction;
use App\Actions\FacturaCreateAction;
use App\Actions\FacturaImprimirAction;
use App\Actions\FacturaReplicarAction;
use App\Models\{Entidad, Facturacion, FacturacionConcepto, MetodoPago};
use Illuminate\Support\Facades\Response;
use Livewire\Component;

// use Illuminate\Support\Facades\DB;

class Prefactura extends Component
{
    public $factura, $conceptos, $facturada, $message, $titulo;
    public $bloqueado=false;
    // $mostrarGenerar=1, $showgenerar, $nf,$pre,
    protected $listeners = [
        'facturaupdate' => '$refresh',
    ];

    protected function rules(){
        return [
            'factura.id'=>'nullable',
            'factura.numfactura'=>'nullable',
            'factura.serie'=>'nullable',
            'factura.entidad_id'=>'required',
            'factura.fechafactura'=>'date|required',
            'factura.fechavencimiento'=>'date|nullable',
            'factura.metodopago_id'=>'numeric|nullable',
            'factura.refcliente'=>'nullable',
            'factura.mail'=>'nullable',
            'factura.enviar'=>'boolean|nullable',
            'factura.enviada'=>'boolean|nullable',
            'factura.pagada'=>'boolean|nullable',
            'factura.facturable'=>'boolean|nullable',
            'factura.facturada'=>'boolean|nullable',
            'factura.asiento'=>'numeric|nullable',
            'factura.fechaasiento'=>'date|nullable',
            'factura.observaciones'=>'nullable',
            'factura.notas'=>'nullable',
            'factura.ruta'=>'nullable',
            'factura.fichero'=>'nullable',
        ];
    }

    public function mount(Facturacion $facturacion)
    {
        $this->factura=$facturacion;
        $this->factura->enviar=1;
        $this->factura->enviada=0;
        $this->factura->pagada=0;
        $this->factura->facturable=1;
        $this->factura->facturada= false;
        $this->conceptos=FacturacionConcepto::where('entidad_id',$facturacion->entidad_id)->get();
        $this->titulo= 'Prefactura ' . $this->factura->id;
    }

    public function render(){
        $entidades=Entidad::where('estado','1')->where('cliente','1')->orderBy('entidad')->get();
        $pagos=MetodoPago::all();
        return view('livewire.prefactura',compact('entidades','pagos',));
    }

    public function updatedFacturaEntidadId()
    {
        $this->conceptos=FacturacionConcepto::where('entidad_id',$this->factura->entidad_id)->get();
        $entidad=Entidad::find($this->factura->entidad_id);
        $mes = date("m", strtotime(date("d-m-Y")));
        $anyo = date("Y", strtotime(date("d-m-Y")));
        if(!$this->factura->fechafactura){
            $this->factura->fechafactura=$entidad->diafactura.'-'.$mes.'-'.$anyo;
            $this->factura->fechavencimiento=$entidad->diavencimiento.'-'.$mes.'-'.$anyo;
        }
        if(!$this->factura->metodopago_id)
            $this->factura->metodopago_id=$entidad->metodopago_id;
        if(!$this->factura->refcliente)
            $this->factura->refcliente=$entidad->refcliente;
        if(!$this->factura->enviar)
            $this->factura->enviar=$entidad->enviar;
    }

    public function save(){
        $this->validate();
        $i=$this->factura->id;
        $fac=Facturacion::updateOrCreate([
            'id'=>$i
            ],
            [
                'numfactura'=>$this->factura->numfactura,
                'serie'=>$this->factura->serie,
                'entidad_id'=>$this->factura->entidad_id,
                'fechafactura'=>$this->factura->fechafactura,
                'fechavencimiento'=>$this->factura->fechavencimiento,
                'metodopago_id'=>$this->factura->metodopago_id,
                'refcliente'=>$this->factura->refcliente,
                'mail'=>$this->factura->mail,
                'enviar'=>$this->factura->enviar,
                'enviada'=>$this->factura->enviada,
                'pagada'=>$this->factura->pagada,
                'facturada'=>$this->factura->facturada,
                'facturable'=>$this->factura->facturable,
                'asiento'=>$this->factura->asiento,
                'fechaasiento'=>$this->factura->fechaasiento,
                'observaciones'=>$this->factura->observaciones,
                'notas'=>$this->factura->notas,
            ]
        );
        $this->emitSelf('notify-saved');
    }

    public function agregarconcepto(FacturacionConcepto $concepto){
        if($this->factura->id){
            $c=FacturaConceptoStoreAction::execute($this->factura,$concepto);
            $this->emit('detallerefresh');
        }else{
            $this->dispatchBrowserEvent('notifyred', 'Debes crear la Pre-factura primero');
        }
    }

    public function creafactura(Facturacion $factura)
    {
        $this->validate([
            'factura.entidad_id'=>'required',
            'factura.id'=>'required',
            'factura.fechafactura'=>'required|date',
            'factura.fechavencimiento'=>'required|date',
            'factura.fechafactura'=>'required',
            'factura.metodopago_id'=>'required',
        ]);

        $fac=new FacturaCreateAction;$f=$fac->execute($factura);
        $fac=new FacturaImprimirAction;$fac->execute($f);
        return redirect( route('facturacion.edit',$f) );
    }

    public function delete($facturacionId)
    {
        $facturaBorrar = Facturacion::find($facturacionId);
        if ($facturaBorrar) {
            $facturaBorrar->delete();
            $this->dispatchBrowserEvent('notify', 'La factura ha sido eliminada!');
        }
    }
}
