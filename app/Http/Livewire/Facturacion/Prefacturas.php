<?php

namespace App\Http\Livewire\Facturacion;

use App\Actions\FacturaCreateAction;
use App\Actions\FacturaImprimirAction;
use App\Actions\PrefacturaCreateAction;
use App\Exports\PrefacturasExport;
use App\Models\{Facturacion,Entidad, FacturacionConcepto, FacturacionDetalleConcepto};
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use App\Http\Livewire\DataTable\WithBulkActions;

class Prefacturas extends Component
{
    use WithPagination, WithBulkActions;

    public $entidad;
    public $search='';
    public $filtrofacturable='1';
    public $filtroanyo='';
    public $filtromes='';
    public $ruta='facturacion.prefacturas';
    public $diafactura;
    public $diavencimiento;
    public $iva;

    public $anyoplan='';

    public $showDeleteModal=false;
    public $showPlanModal='0';

    protected function rules(){
        return [
            'filtroanyo'=>'required',
            'filtromes'=>'required',
            'filtrofacturable'=>'in:1',
        ];
    }

    public function mount(Entidad $entidad,$ruta){
        $this->filtroanyo=date('Y');
        if($entidad->id)
            $this->filtromes='';
        else
            $this->filtromes=intval(date('m'));
        $this->entidad=$entidad;
        $this->ruta=$ruta;

    }

    public function render(){
        if($this->selectAll) $this->selectPageRows();
        $facturaciones = $this->rows;
        return view('livewire.facturacion.prefacturas',compact('facturaciones'));
    }

    public function getRowsQueryProperty(){
        return Facturacion::query()
            ->with('metodopago','ciclo')
            ->join('entidades','facturacion.entidad_id','=','entidades.id')
            ->join('facturacion_detalles','facturacion_detalles.facturacion_id','=','facturacion.id')
            ->join('facturacion_detalle_conceptos','facturacion_detalle_conceptos.facturaciondetalle_id','=','facturacion_detalles.id')
            // ->select('facturacion.id','facturacion.fechafactura','facturacion.ciclo_id','facturacion.fechavencimiento','facturacion.enviada','facturacion.facturada',
            ->select('facturacion.*',
                    'entidades.entidad','entidades.emailadm',
                    DB::raw('sum(facturacion_detalle_conceptos.base) as totalesbase'),
                    DB::raw('sum(facturacion_detalle_conceptos.exenta) as totalesexenta'),
                    DB::raw('sum(facturacion_detalle_conceptos.totaliva) as totalesiva'),
                    DB::raw('sum(facturacion_detalle_conceptos.total) as totalestotal')
                    )
            ->groupBy('facturacion.id')
            ->where(function ($query){
                $query->where('numfactura','')
                    ->orWhere('numfactura',null);
            })
            ->when($this->filtrofacturable!='', function ($query){
                $query->where('facturable',$this->filtrofacturable);
                })
            ->when($this->entidad->id!='', function ($query){
                $query->where('entidad_id',$this->entidad->id);
                })
            ->searchYear('fechafactura',$this->filtroanyo)
            ->searchMes('fechafactura',$this->filtromes)
            ->search('entidades.entidad',$this->search)
            ->orderBy('facturacion.fechafactura')
            ->orderBy('entidades.entidad');
            // ->paginate(5); solo contemplo la query, no el resultado. Luego pongo el resultado: get, paginate o lo que quiera
    }

    public function getRowsProperty(){
        return $this->rowsQuery->paginate(100);
    }

    public function generarSelected(){
        $prefacturas = $this->selectedRowsQuery->get();
        $this->validate();

        foreach ($prefacturas as $prefactura) {
            $fac=new FacturaCreateAction;$f=$fac->execute($prefactura);

            $f->pdffactura($f);
            // $fac=new FacturaImprimirAction;$fac->execute($f);
        }
        return redirect()->route('facturacion.index');
    }

    public function generarplan(){
        $this->diafactura=$this->entidad->diafactura;
        $this->diavencimiento=$this->entidad->diavencimiento;
        $this->iva=$this->entidad->ivagral;
        $this->validate([
            'anyoplan'=>'required|digits:4|integer|gt:2022|max:'.(date('Y')+1),
            'diafactura'=>'required',
            'diavencimiento'=>'required',
            'iva'=>'required',
        ],[
            'anyoplan.required'=>'Es necesario el año del plan a generar',
            'anyoplan.max'=>'Solo se puede generar el plan del año próximo o actual',
            'anyoplan.min'=>'El año debe ser superior a 2022',
            'anyoplan.integer'=>'El año debe ser numérico y tener 4 dígitos',
            'anyoplan.digits'=>'El año debe ser numérico y tener 4 dígitos',
            'diafactura.required'=>'Hay que rellenar el dia de factura en la ficha del cliente',
            'diavencimiento.required'=>'Hay que rellenar el dia de vencimientos en la ficha del cliente',
            'iva.required'=>'Hay que rellenar el iva en la ficha del cliente',
        ]);


        $agrupacion=FacturacionConcepto::where('entidad_id',$this->entidad->id)->groupBy('concepto')->get();
        // dd($agrupacion);
        $conceptos=FacturacionConcepto::where('entidad_id',$this->entidad->id)->get();
        foreach ($conceptos as $concepto) {
            $prefac=new PrefacturaCreateAction; $p=$prefac->execute($concepto,$this->entidad,$this->anyoplan);
        }
        $this->anyoplan='';
        $this->showPlanModal=false;
    }

    public function exportSelected(){
        //toCsv es una macro a n AppServiceProvider
        return response()->streamDownload(function(){
            echo $this->selectedRowsQuery->toCsv();
        },'prefacturas.csv');

        $this->dispatchBrowserEvent('notify', 'CSV Prefacturas descargado!');
    }

    public function exportXls(){

        $prefacturas= Facturacion::query()
            ->join('entidades','facturacion.entidad_id','=','entidades.id')
            ->join('facturacion_detalles','facturacion.id','=','facturacion_id')
            ->join('facturacion_detalle_conceptos','facturacion_detalles.id','=','facturaciondetalle_id')
            ->join('metodo_pagos','entidades.metodopago_id','=','metodo_pagos.id')
            ->select( 'entidades.entidad',
                'facturacion.fechafactura','facturacion.fechavencimiento',
                'facturacion_detalles.concepto',
                'facturacion_detalle_conceptos.concepto','facturacion_detalle_conceptos.base',
                'facturacion_detalle_conceptos.exenta','facturacion_detalle_conceptos.iva','facturacion_detalle_conceptos.total',
                'entidades.iban1','metodo_pagos.metodopagocorto')
            ->searchYear('fechafactura',$this->filtroanyo)
            ->searchMes('fechafactura',$this->filtromes)
            ->orderBy('facturacion.fechafactura')
            ->orderBy('entidades.entidad')
            ->get();

            return Excel::download(new PrefacturasExport (
                $prefacturas,
            ), 'prefacturas.xlsx');
    }

    public function deleteSelected(){
        // $prefacturas=Facturacion::findMany($this->selected); funciona muy bien

        $deleteCount = $this->selectedRowsQuery->count();
        $this->selectedRowsQuery->delete();
        $this->showDeleteModal = false;

        $this->dispatchBrowserEvent('notify', $deleteCount . ' Prefacturas eliminadas!');
    }

    public function delete($facturacionId){
        $facturacion = Facturacion::find($facturacionId);
        if ($facturacion) {
            $facturacion->delete();
            // session()->flash('message', $facturacion->entidad.' eliminado!');
            $this->dispatchBrowserEvent('notify', 'La línea de pre-factura: '.$facturacion->id.'-'.$facturacion->numfactura.' ha sido eliminada!');
        }
    }
}


