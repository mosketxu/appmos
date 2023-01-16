<?php

namespace App\Http\Livewire\Facturacion;

use App\Models\{Facturacion,FacturacionDetalle,FacturacionDetalleConcepto};
use App\Actions\FacturaImprimirAction;
use Livewire\Component;

class FacturaDetalleConceptos extends Component
{
    public $detalleid;
    public $detalle;
    public $color;
    public $orden='0';
    public $concepto='';
    public $tipo='0';
    public $subcuenta='705000';
    public $uds='1';
    public $importe='0';
    public $piva='0.21  ';
    public $iva='0.00';
    public $subtotal='0.00';
    public $base='0.00';
    public $exenta='0.00';
    public $total='0.00';
    public $deshabilitado='';


    protected function rules(){
        return [
            'concepto'=>'required',
        ];
    }

    public function mount($detalle,$color,$deshabilitado){
        $this->detalleid=$detalle->id;
        $this->detalle=$detalle;
        $this->color='bg-'.$color.'-50';
        $this->$deshabilitado=$deshabilitado;
    }

    public function render(){
        // $tipos=App\Models\FacturacionDetalle::TIPOS;
        $tipos=FacturacionDetalle::TIPOS;
        $dconceptos=FacturacionDetalleConcepto::where('facturaciondetalle_id',$this->detalleid)->get();
        return view('livewire.facturacion.factura-detalle-conceptos',compact('dconceptos','tipos'));
    }

    public function calculo(){
        $this->piva=trim($this->piva);
        $this->piva=$this->tipo=='1' ? '0.00' : $this->piva;
        $this->iva=round($this->uds * $this->importe * $this->piva,2);
        $this->total=round($this->uds*$this->importe*(1+$this->piva),2);
        $this->base=$this->piva!='0.00' ? round($this->uds*$this->importe,2) : '0.00';
        $this->exenta=$this->piva=='0.00' ? round($this->uds*$this->importe,2) : '0.00';
        $this->subtotal=round($this->uds*$this->importe,2);

    }

    public function updatedTipo(){$this->calculo();}
    public function updatedPiva(){$this->calculo();}
    public function updatedUds(){$this->calculo();}
    public function updatedImporte(){$this->calculo();}

    public function save(){
        $this->calculo();
        $fdc=FacturacionDetalleConcepto::create([
            'facturaciondetalle_id'=>$this->detalleid,
            'orden'=>$this->orden=='' ? '0' : $this->orden  ,
            'concepto'=>$this->concepto,
            'tipo'=>$this->tipo,
            'subcuenta'=>$this->subcuenta,
            'unidades'=>$this->uds,
            'importe'=>$this->importe,
            'iva'=>$this->piva,
            'base'=>$this->base,
            'exenta'=>$this->exenta,
            'totaliva'=>$this->iva,
            'total'=>$this->total,
        ]);

        $this->orden='0';
        $this->concepto='';
        $this->tipo='0';
        $this->subcuenta='705000';
        $this->uds='1';
        $this->importe='0';
        $this->piva='0.21  ';
        $this->iva='0.00';
        $this->total='0.00';

        $notification = array(
            'message' => 'Concepto añadido.',
            'alert-type' => 'success'
        );
        $factura=Facturacion::find($this->detalle->facturacion_id);

        $factura->pdffactura($factura);

        // $fac=new FacturaImprimirAction;
        // $fac->execute($factura);

        $vista =($factura->numfactura!='' || !is_null($factura->numfactura)) ? "facturacion.edit": 'facturacion.editprefactura';
        return redirect()->route($vista,$this->detalle->facturacion_id);

    }

    public function delete($conceptoid)
    {
        $borrar = FacturacionDetalleConcepto::find($conceptoid);

        if ($borrar) {
            $borrar->delete();
            $this->dispatchBrowserEvent('notify', 'Concepto eliminado!');

            $factura=Facturacion::find($this->detalle->facturacion_id);

            $factura->pdffactura($factura);
            // $fac=new FacturaImprimirAction;
            // $fac->execute($factura);
        }

    }
}
