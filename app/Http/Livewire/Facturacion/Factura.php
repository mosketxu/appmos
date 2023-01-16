<?php

namespace App\Http\Livewire\Facturacion;

// use App\Actions\MonthQuarterAction;

use App\Actions\FacturaConceptoStoreAction;
use App\Actions\FacturaCreateAction;
use App\Actions\FacturaImprimirAction;
use App\Models\{Entidad, Facturacion, FacturacionConcepto, MetodoPago, FacturacionDetalle};
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

use function PHPUnit\Framework\isNull;

class Factura extends Component
{
    public $factura, $conceptos, $facturada, $message, $showgenerar, $nf,$pre,$titulo;
    public $existe='0';
    public $bloqueado=false;
    public $ruta='facturacion.prefacturas';


    protected $listeners = [
        'facturaupdate' => '$refresh',
    ];

    protected function rules(){
        return [
            'factura.id'=>'nullable',
            'factura.numfactura'=>'nullable',
            'factura.serie'=>'required',
            'factura.entidad_id'=>'required',
            'factura.fechafactura'=>'date|required',
            'factura.fechavencimiento'=>'date|required',
            'factura.metodopago_id'=>'numeric|required',
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
        $this->showgenerar = $facturacion->facturada ? false : true;
        $this->nf=$this->factura->serie.'-'.substr($this->factura->numfactura,-5) ;
        $this->conceptos=FacturacionConcepto::where('entidad_id',$facturacion->entidad_id)->get();
        $this->bloqueado=$this->factura->facturada==true ? 'disabled' : '';
        $this->titulo= $this->pre=='no'? 'Factura ' . $this->nf : 'Prefactura ' . $this->factura->id;
    }

    public function render(){
        $this->existe=(Storage::exists('public/'.$this->factura->rutafichero) && ($this->factura->rutafichero!='/')) ? '1' : '0';

        $entidades=Entidad::where('estado','1')->where('cliente','1')->where('facturar','1')->orderBy('entidad')->get();
        $pagos=MetodoPago::all();
        return view('livewire.facturacion.factura',compact('entidades','pagos',));
    }

    public function updatedFacturaFechafactura(){
        $this->factura->serie= substr($this->factura->fechafactura->format('Y'), -2);
        if($this->factura->entidad_id ){
            $ent=Entidad::find($this->factura->entidad_id);
            $dia = date("d", strtotime($this->factura->fechafactura));
            $mes = date("m", strtotime($this->factura->fechafactura));
            //miro si la fecha me obliga a pasar de mes
            if($ent->diavencimiento<$dia)
                $mes=$mes+1;
            $anyo = date("Y", strtotime($this->factura->fechafactura));
            $this->factura->fechavencimiento=$ent->diavencimiento.'-'.$mes.'-'.$anyo;
        }

    }

    public function updatedFacturaEntidadId()
    {
        $entidad=Entidad::find($this->factura->entidad_id);
        $this->conceptos=FacturacionConcepto::where('entidad_id',$entidad->id)->get();
        $this->factura->serie=$entidad->metodopago_id;
        $this->factura->metodopago_id=$entidad->metodopago_id;
        $this->factura->refcliente=$entidad->refcliente;
        $this->factura->enviar=$entidad->enviar;
    }

    public function updatedFacturaFacturada(){
            $f=Facturacion::find($this->factura->id);
            $f->facturada=$this->factura->facturada;
            $f->save();
            $this->redirect( route('facturacion.edit',$f) );
    }

    public function save(){
        $this->validate();

        $i=$this->factura->id? $this->factura->id : '0';
        $detalles=FacturacionDetalle::where('facturacion_id',$i)->count();
        if($i!='0' && $detalles>0)
            $this->factura->facturada='1';
        else
            $this->factura->facturada='0';

        $f=Facturacion::updateOrCreate([
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
            'enviar'=>$this->factura->enviar ?? '0',
            'enviada'=>$this->factura->enviada ?? '0',
            'pagada'=>$this->factura->pagada ?? '0',
            'facturada'=>$this->factura->facturada,
            'facturable'=>$this->factura->facturable ?? '1',
            'asiento'=>$this->factura->asiento,
            'fechaasiento'=>$this->factura->fechaasiento,
            'observaciones'=>$this->factura->observaciones,
            'notas'=>$this->factura->notas,
        ]);


        $fac=new FacturaCreateAction;
        $fac->execute($f);

        $f->pdffactura($f);

        return redirect()->route('facturacion.edit',$f);
    }

    public function agregarconcepto(FacturacionConcepto $concepto){
        if($this->factura->id){
            $fac= new FacturaConceptoStoreAction;
            $fac->execute($this->factura,$concepto);
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
        $fac=new FacturaCreateAction;
        $fac->execute($factura);

        $factura->pdffactura($factura);
        // $fac=new FacturaImprimirAction;
        // $fac->execute($factura);
        $this->redirect( route('facturacion.edit',$factura) );
    }


    public function delete($facturacionId){
        $facturaBorrar = Facturacion::find($facturacionId);

        if ($facturaBorrar) {
            $facturaBorrar->delete();
            $this->dispatchBrowserEvent('notify', 'La factura ha sido eliminada!');
        }
    }
}
