<?php

namespace App\Http\Livewire\Facturacion;

use App\Actions\FacturaReplicarAction;
use App\Exports\FacturacionControlExport;
use App\Exports\RemesaExport;
use App\Models\{Facturacion,Entidad};
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;
use App\Http\Livewire\DataTable\WithBulkActions;
use App\Mail\MailFactura;
use ZipArchive;
use File;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;

class Facturaciones extends Component
{
    use WithPagination, WithBulkActions;

    public $search='';
    public $filtrofacturado='';
    public $filtrofacturacionPrefacturacion='1';
    public $filtrocontabilizado='';
    public $filtroenviada='';
    public $filtroenviar='';
    public $filtroremesa='';
    public $filtropagada='';
    public $filtroanyo='';
    public $filtromes='';
    public $entidad;
    public $message;
    public $ruta='facturacion.show';
    public $showDeleteModal;

    protected function rules(){
        return[
            'filtroanyo'=>'required',
            'filtromes'=>'required',
        ];
    }

    public function mount(Entidad $entidad, $ruta)
    {
        $this->filtroanyo=date('Y');
        $this->filtromes=intval(date('m'));
        $this->entidad=$entidad;
        $this->ruta=$ruta;
    }

    public function render(){
        if($this->selectAll) $this->selectPageRows();
        $facturaciones = $this->rows;
        // $totales= Facturacion::query()
        // ->facturas($this->filtroenviada, $this->filtropagada, $this->filtrofacturado,$this->filtroanyo,$this->filtromes ,$this->search)
        // ->first();
        return view('livewire.facturacion.facturaciones',compact('facturaciones'));
    }

    public function replicateFactura($facturaId){
        $factura=Facturacion::find($facturaId);
        $fac=new FacturaReplicarAction;
        $f=$fac->execute($factura);
        return redirect()->route('facturacion.editprefactura',$f);
    }

    public function getRowsQueryProperty(){
        return Facturacion::query()
            ->with('metodopago')
            ->join('entidades','facturacion.entidad_id','=','entidades.id')
            ->leftJoin('facturacion_detalles','facturacion_detalles.facturacion_id','=','facturacion.id')
            ->leftJoin('facturacion_detalle_conceptos','facturacion_detalle_conceptos.facturaciondetalle_id','=','facturacion_detalles.id')
            // ->select('facturacion.*', 'entidades.entidad', 'entidades.nif','entidades.emailadm')
            ->select('facturacion.*',
                    'entidades.entidad','entidades.emailadm',
                    DB::raw('sum(facturacion_detalle_conceptos.base) as totalesbase'),
                    DB::raw('sum(facturacion_detalle_conceptos.exenta) as totalesexenta'),
                    DB::raw('sum(facturacion_detalle_conceptos.totaliva) as totalesiva'),
                    DB::raw('sum(facturacion_detalle_conceptos.total) as totalestotal')
                    )
            ->groupBy('facturacion.id')
            ->where('numfactura','<>','')
            ->when($this->filtroenviada!='', function ($query){
                $query->where('enviada',$this->filtroenviada);
                })
            ->when($this->filtroenviar!='', function ($query){
                $query->where('entidades.enviar',$this->filtroenviar);
                })
            ->when($this->filtropagada!='', function ($query){
                $query->where('pagada',$this->filtropagada);
                })
            ->when($this->entidad->id!='', function ($query){
                $query->where('entidad_id',$this->entidad->id);
                })
            ->when($this->filtroremesa!='', function ($query){
                $query->where('fechavencimiento',$this->filtroremesa);
                })
            ->when($this->filtrocontabilizado!='', function ($query){
                if($this->filtrocontabilizado=='0'){
                    $query->where('asiento','0');
                }else{
                    $query->where('asiento','>','0');
                }
            })
            ->searchYear('fechafactura',$this->filtroanyo)
            ->searchMes('fechafactura',$this->filtromes)
            ->search('entidades.entidad',$this->search)
            ->orSearch('facturacion.numfactura',$this->search)
            ->orderBy('facturacion.numfactura','desc')
            ->orderBy('facturacion.id','desc');
            // ->paginate(5); solo contemplo la query, no el resultado. Luego pongo el resultado: get, paginate o lo que quiera
    }

    public function getRowsProperty(){
        return $this->rowsQuery->paginate(100);
    }

    public function zipSelected(){
        $this->validate();
        $zip = new ZipArchive;
        $fileName = 'myNewFile.zip';
        $ruta='storage/facturas/'.substr($this->filtroanyo, -2).'/'.substr($this->filtromes+100, -2).'/';

        if (!file_exists($ruta)) {
            $message="La ruta no existe";
            session()->flash('message', 'No existe este directorio.');
        } else {
            $zip->open($fileName, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
            // if ($zip->open(public_path($fileName), ZipArchive::CREATE) === true) {
                $files = File::files(public_path($ruta));

                foreach ($files as $key => $value) {
                    $relativeNameInZipFile = basename($value);
                    $zip->addFile($value, $relativeNameInZipFile);
                }

                $zip->close();
            // }
            return response()->download(public_path($fileName))->deleteFileAfterSend();
        }
    }

    public function mailSelected(){
        $conproblemas=[];
        $sinproblemas=0;
        $facturas = $this->selectedRowsQuery->get();

        foreach ($facturas as $factura) {
            $fileName='storage/'.$factura->rutafichero;
            if (!file_exists($fileName) || !$factura->mail) {
                array_push($conproblemas,$factura->factura5);
            }else{
                $sinproblemas++;
                Mail::to($factura->mail)
                ->bcc('alex.arregui@hotmail.es')
                ->send(new MailFactura($factura));
                // ->queue(new MailFactura($factura);
                $factura->enviada=true;
                $factura->save();
            }
        }
        $conproblema=$conproblemas ? json_encode($conproblemas): 0;

        session()->flash('message', 'Mails enviados correctamente: '. $sinproblemas. ', Con problemas: '. $conproblema);
        return redirect(route('facturacion.index'));
        // $this->dispatchBrowserEvent('notify', 'Mails enviados correctamente: '. $sinproblemas. ', Con problemas:'. $conproblemas);
    }


    public function exportSelected(){
        //toCsv es una macro a n AppServiceProvider
        return response()->streamDownload(function(){
            echo $this->selectedRowsQuery->toCsv();
        },'prefacturas.csv');
        $this->dispatchBrowserEvent('notify', 'CSV Facturas descargado!');
    }

    public function exportControlSelected(){

        $control = Facturacion::query()
            ->with('entidad')
            ->join('metodo_pagos','facturacion.metodopago_id','=','metodo_pagos.id')
            ->join('entidades','facturacion.entidad_id','=','entidades.id')
            ->leftJoin('facturacion_detalles','facturacion_detalles.facturacion_id','=','facturacion.id')
            ->leftJoin('facturacion_detalle_conceptos','facturacion_detalle_conceptos.facturaciondetalle_id','=','facturacion_detalles.id')
            ->select('entidades.cuentacontable','entidades.entidad',
                // DB::raw('sum(facturacion_detalle_conceptos.base) as base'),
                DB::raw("SUM( ( CASE WHEN facturacion_detalle_conceptos.tipo = 0 THEN facturacion_detalle_conceptos.base END ) ) AS Base"),
                DB::raw("SUM( ( CASE WHEN facturacion_detalle_conceptos.tipo = 2 THEN facturacion_detalle_conceptos.base END ) ) AS Otros"),
                DB::raw('sum(facturacion_detalle_conceptos.exenta) as exenta'),
                DB::raw('sum(facturacion_detalle_conceptos.totaliva) as iva'),
                DB::raw('sum(facturacion_detalle_conceptos.total) as total'),
                'facturacion.numfactura','facturacion.fechavencimiento','metodo_pagos.metodopagocorto',
                'entidades.porcentajemarta','entidades.porcentajesusana',
            )

            ->groupBy('facturacion.id')
            ->where('numfactura','<>','')
            ->searchYear('fechafactura',$this->filtroanyo)
            ->searchMes('fechafactura',$this->filtromes)
            ->get();

            $fichero='control-'.$this->filtroanyo.'-'.$this->filtromes.'.xlsx';

            return Excel::download(new FacturacionControlExport (
                $control,
            ), $fichero);

    }

    public function exportRemesa(){
        $nada='';
        $f=Facturacion::find('66');
        $a="Fra.";
        $remesa= Facturacion::query()
        ->join('entidades','facturacion.entidad_id','=','entidades.id')
        ->join('facturacion_detalles','facturacion_detalles.facturacion_id','=','facturacion.id')
        ->join('facturacion_detalle_conceptos','facturacion_detalle_conceptos.facturaciondetalle_id','=','facturacion_detalles.id')
        ->select("entidades.entidad as empresacli","entidades.iban1 as iban","entidades.id as mandato","facturacion.fechafactura",
            DB::raw("sum(facturacion_detalle_conceptos.total) as importe"),
            DB::raw("CONCAT('Fra. ',facturacion.numfactura,' Suma') as numfra"),
            DB::raw("'' AS bicswift"),
            DB::raw("'RCUR' AS rcur"),
            "facturacion.fechavencimiento as fv",
            "facturacion.numfactura as IdFactura",
            "facturacion.metodopago_id")
        ->groupBy('facturacion.id')
        ->where('fechavencimiento',$this->filtroremesa)
        ->where('facturacion.metodopago_id','2')
        ->orderBy('entidades.entidad')
        ->get();

        return Excel::download(new RemesaExport (
            $remesa,
        ), 'remesa.xlsx');

    }

    public function deleteSelected(){
        // $prefacturas=Facturacion::findMany($this->selected); funciona muy bien

        $deleteCount = $this->selectedRowsQuery->count();
        $this->selectedRowsQuery->delete();
        $this->showDeleteModal = false;

        $this->dispatchBrowserEvent('notify', $deleteCount . ' Facturas eliminadas!');
    }

    public function delete($facturacionId)
    {
        $facturacion = Facturacion::find($facturacionId);
        if ($facturacion) {
            $facturacion->delete();
            // session()->flash('message', $facturacion->entidad.' eliminado!');
            $this->dispatchBrowserEvent('notify', 'La línea de facturación: '.$facturacion->id.'-'.$facturacion->numfactura.' ha sido eliminada!');
        }
    }
}
