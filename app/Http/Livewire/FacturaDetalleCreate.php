<?php

namespace App\Http\Livewire;

use App\Models\{Facturacion, FacturacionDetalle};
use Illuminate\Validation\Rule;


use Livewire\Component;

class FacturaDetalleCreate extends Component
{
    public $facturacion;
    public $detalle;

    protected $rules = [
        'facturacion.id' => 'required',
        'detalle.facturacion_id'=>'numeric',
        'detalle.orden'=>'numeric|required',
        'detalle.tipo'=>'required|numeric',
        'detalle.concepto'=>'nullable|max:250',
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
        $this->detalle->iva=0;
        $this->detalle->subcuenta=0;
        $this->detalle->pagadopor=0;
        $facturacion=$this->facturacion->id;
    }

    public function render()
    {
        $detalle=$this->detalle;
        $facturacion=$this->facturacion;
        return view('livewire.factura-detalle-create');
    }

    public function save()
    {
        // dd($this->detalle);
        if($this->detalle){
            // dd('l');
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

            $this->emit('detalleupdate');
            // $this->detalle=$detalle;
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
    }

}


