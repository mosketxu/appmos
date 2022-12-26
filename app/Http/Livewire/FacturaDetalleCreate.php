<?php

namespace App\Http\Livewire;

use App\Models\{Facturacion, FacturacionDetalle};


use Livewire\Component;

class FacturaDetalleCreate extends Component
{
    public $facturacion;
    public $fprueba;
    public $detalle;

    protected $rules = [
        'facturacion.id' => 'required',
        'detalle.facturacion_id'=>'numeric',
        'detalle.orden'=>'numeric|required',
        'detalle.tipo'=>'required|numeric',
        'detalle.concepto'=>'required|max:250',
        'detalle.unidades'=>'numeric|required',
        'detalle.coste'=>'numeric|required',
        'detalle.iva'=>'numeric|required',
        'detalle.subcuenta'=>'numeric|required',
        'detalle.pagadopor'=>'numeric|required',
    ];

    public function mount(FacturacionDetalle $detalle)
    {
        $this->detalle=$detalle;
        $this->detalle->facturacion_id=$this->facturacion->id;
        $this->detalle->orden=0;
        $this->detalle->tipo=0;
        $this->detalle->unidades=1;
        $this->detalle->coste=0;
        $this->detalle->iva='0.21';
        $this->detalle->subcuenta='705000';
        $this->detalle->pagadopor=0;
    }

    public function render()
    {
        return view('livewire.factura-detalle-create');
    }

    public function updatedDetalleTipo()
    {
        switch ($this->detalle->tipo) {
            case '0':
                $this->detalle->iva='0.21';
                $this->detalle->subcuenta='705000';
                break;
            case '1':
                $this->detalle->iva='0.00';
                $this->detalle->subcuenta='759000';
                break;
            case '2':
                $this->detalle->iva='0.21';
                $this->detalle->subcuenta='759000';
                break;
            default:
            $this->detalle->iva='0.21';
            $this->detalle->subcuenta='705000';
            break;
        }
    }

    public function save()
    {
if($this->detalle){
            $this->validate();
            FacturacionDetalle::create([
                'facturacion_id'=>$this->detalle->facturacion_id,
                'orden'=>$this->detalle->orden,
                'tipo'=>$this->detalle->tipo,
                'concepto'=>$this->detalle->concepto,
                'unidades'=>$this->detalle->unidades,
                'coste'=>$this->detalle->coste,
                'iva'=>$this->detalle->iva,
                'subcuenta'=>$this->detalle->subcuenta,
                'pagadopor'=>$this->detalle->pagadopor,
                ]);
                $this->dispatchBrowserEvent('notify', 'Detalle añadido con éxito');

            $this->emit('detallerefresh');
            $this->detalle->facturacion_id=$this->facturacion->id;
            $this->detalle->orden=0;
            $this->detalle->concepto='';
            $this->detalle->tipo=0;
            $this->detalle->unidades=1;
            $this->detalle->coste=0;
            $this->detalle->iva=0;
            $this->detalle->subcuenta=0;
            $this->detalle->pagadopor=0;
        }

        $f=Facturacion::find($this->detalle->facturacion_id);
        if($f->numfactura) $f->imprimirfactura();
    }


}


