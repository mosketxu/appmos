<?php

namespace App\Http\Livewire\Facturacion;

use App\Actions\FacturaCreateAction;
use App\Actions\FacturaImprimirAction;
use App\Actions\PrefacturaCreateAction;
use App\Exports\PrefacturasExport;
use App\Models\{Facturacion,Entidad, FacturacionConcepto};
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

    public function mount(Entidad $entidad){
        $this->filtroanyo=date('Y');
        // $this->filtromes=intval(date('m'));
        $this->entidad=$entidad;
    }

    public function render(){
        if($this->selectAll) $this->selectPageRows();
        $facturaciones = $this->rows;
        return view('livewire.facturacion.prefacturas',compact('facturaciones'));
    }

    public function getRowsQueryProperty(){
        return Facturacion::query()
            ->with('metodopago')
            ->join('entidades','facturacion.entidad_id','=','entidades.id')
            ->select('facturacion.*', 'entidades.entidad', 'entidades.nif','entidades.emailadm')
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
            $fac=new FacturaImprimirAction;$fac->execute($f);
        }
        return redirect()->route('facturacion.index');
    }

    public function generarplan(){
        $this->validate([
            'anyoplan'=>'required|digits:4|integer|min:2022|max:'.(date('Y')+1),
        ],[
            'anyoplan.required'=>'Es necesario el año del plan a generar',
            'anyoplan.max'=>'Solo se puede generar el plan del año próximo o actual',
            'anyoplan.min'=>'El año debe ser superior a 2022',
            'anyoplan.integer'=>'El año debe ser numérico y tener 4 dígitos',
            'anyoplan.digits'=>'El año debe ser numérico y tener 4 dígitos',
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
            ->select( 'entidades.entidad',
                'facturacion.fechafactura','facturacion.fechavencimiento',
                'facturacion_detalles.concepto',
                'facturacion_detalle_conceptos.concepto','facturacion_detalle_conceptos.base',
                'facturacion_detalle_conceptos.exenta','facturacion_detalle_conceptos.iva','facturacion_detalle_conceptos.total')
            ->searchYear('fechafactura',$this->filtroanyo)
            ->searchMes('fechafactura',$this->filtromes)
            ->orderBy('facturacion.fechafactura')
            ->orderBy('entidades.entidad')
            ->get();

            return Excel::download(new PrefacturasExport (
                $prefacturas,
            ), 'prefacturas.xlsx');


        // return Excel::download(new ExportPresupuestos(
        //         $this->mesanyo,
        //         $sel,
        //         $est,
        //         $ent,
        //         $sol,
        //         $this->filtroFi,
        //         $this->filtroFf,
        //         $this->filtroventasIni,
        //         $this->filtroventasFin,
        //         $filas,
        //     ), 'estadisticapresupuestos.xlsx');
return response()->streamDownload(function(){
    echo $csv->toCsv();
},'prefacturas.csv');


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


