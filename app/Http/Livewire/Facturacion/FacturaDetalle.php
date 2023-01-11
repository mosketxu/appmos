<?php

namespace App\Http\Livewire\Facturacion;

use App\Models\{Facturacion,FacturacionDetalle, FacturacionDetalleConcepto};
use Illuminate\Support\Facades\DB;

use Livewire\Component;

class FacturaDetalle extends Component
{
    public $facturacion, $base,$base04,$base10,$base21,$iva04,$iva10,$iva21, $exenta,$suplido, $totaliva, $total;
    public $editedDetalleIndex = null;
    public $editedDetalleField = null;
    public $detalles=[];
    public $showcrear=false;
    public $deshabilitado='';

    protected $listeners = [ 'funshow'=>'funshowdetalle','detallerefresh' => '$refresh','detallerefreshPP'=>'prueba'];

    protected $rules = [
        'detalles.*.orden' => ['numeric'],
        'detalles.*.tipo' => ['numeric'],
        'detalles.*.concepto' => ['max:150'],
        'detalles.*.unidades' => ['numeric'],
        'detalles.*.importe' => ['numeric'],
        'detalles.*.iva' => ['numeric'],
        'detalles.*.subcuenta' => ['numeric'],
        'detalles.*.pagadopor' => ['numeric'],
        'base'=>'nullable',
    ];

    public function mount(Facturacion $factura){
        // $this->detalles = FacturacionDetalle::where('facturacion_id', $this->facturacion->id)
        //     ->orderBy('orden')
        //     ->get()
        //     ->toArray();
    }

    public function render(){

        // dd($this->facturacion->facturada);
        $this->showcrear=$this->facturacion->facturada =='0'? true : false;
        $this->deshabilitado= $this->showcrear==true ? '' : 'disabled' ;
        $factura=Facturacion::with('conceptos')->find($this->facturacion->id);
        if($this->facturacion->id){
            $this->base=$factura->conceptos->sum('base');
            $this->base21=$factura->conceptos->where('iva','0.21')->sum('base');
            $this->iva21=$factura->conceptos->where('iva','0.21')->sum('totaliva');
            $this->base10=$factura->conceptos->where('iva','0.10')->sum('base');
            $this->iva10=$factura->conceptos->where('iva','0.10')->sum('totaliva');
            $this->base04=$factura->conceptos->where('iva','0.04')->sum('base');
            $this->iva04=$factura->conceptos->where('iva','0.04')->sum('totaliva');
            $this->exenta=$factura->conceptos->where('tipo','!=','1')->sum('exenta');
            $this->suplido=$factura->conceptos->where('tipo','1')->sum('exenta');
            $this->totaliva=$factura->conceptos->sum('totaliva');
            $this->total=$factura->conceptos->sum('total');
        }

        $a=FacturacionDetalle::select('id')->where('facturacion_id', $this->facturacion->id)->orderBy('orden')->get();
        $a=$a->toArray();
        $fdc=FacturacionDetalleConcepto::whereIn('facturaciondetalle_id',$a)->get();

        $this->detalles = FacturacionDetalle::where('facturacion_id', $this->facturacion->id)->orderBy('orden')->get();

        $showcrear=$this->showcrear;
        return view('livewire.facturacion.factura-detalle',compact(['factura','showcrear']));
    }

    public function funshowdetalle(){
        $this->showcrear=true;
        $this->emit('detallerefresh');
    }

    public function editDetalle($detalleIndex){
        $this->editedDetalleIndex = $detalleIndex;
    }

    public function editDetalleField($detalleIndex, $fieldName){
        $this->editedDetalleField = $detalleIndex . '.' . $fieldName;
    }

    public function saveDetalle($detalleIndex){
        $this->validate();

        $detalle = $this->detalles[$detalleIndex] ?? NULL;
        if (!is_null($detalle)) {
            $p=FacturacionDetalle::find($detalle['id']);
            $p->orden=$detalle['orden'];
            $p->tipo=$detalle['tipo'];
            $p->concepto=$detalle['concepto'];
            $p->unidades=$detalle['unidades'];
            $p->importe=$detalle['importe'];
            if($p->tipo=='1')
                $p->iva='0';
            else
                $p->iva=$detalle['iva'];
            if($p->tipo=='2')
                $p->subcuenta='759000';
            else
                $p->subcuenta=$detalle['subcuenta'];
            $p->pagadopor=$detalle['pagadopor'];
            $p->save();
        }
        $this->editedDetalleIndex = null;
        $this->editedDetalleField = null;
        $f=Facturacion::find($p->facturacion_id);
        if($f->numerofactura) $f->imprimirfactura();
        $this->emit('detallerefresh');
    }

    public function delete($facturadetalleId)
    {
        $facturadetalleBorrar = FacturacionDetalle::find($facturadetalleId);

        if ($facturadetalleBorrar) {
            $facturadetalleBorrar->delete();
            $f=Facturacion::find($facturadetalleBorrar->facturacion_id);
            if($f->numfactura) $f->imprimirfactura();

            $this->dispatchBrowserEvent('notify', 'Detalle de factura eliminado!');
        }
    }

}
