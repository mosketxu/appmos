<?php

namespace App\Http\Livewire;

use App\Models\{Facturacion,Entidad};
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;
use App\Http\Livewire\DataTable\WithBulkActions;
use App\Mail\MailFactura;
use ZipArchive;
use File;
use Illuminate\Support\Facades\Mail;


class Facturaciones extends Component
{
    use WithPagination, WithBulkActions;

    public $search='';
    public $filtrofacturado='';
    public $filtrocontabilizado='';
    public $filtroenviada='';
    public $filtropagada='';
    public $filtroanyo='';
    public $filtromes='';
    public $entidad;

    public $showDeleteModal=false;

    protected function rules(){
        return[
            'filtroanyo'=>'required',
            'filtromes'=>'required',
        ];
    }

    public function mount(Entidad $entidad)
    {
        $this->filtroanyo=date('Y');
        $this->filtromes=intval(date('m'));
        $this->entidad=$entidad;
    }

    public function getRowsQueryProperty(){
        return Facturacion::query()
            ->join('entidades','facturacion.entidad_id','=','entidades.id')
            ->select('facturacion.*', 'entidades.entidad', 'entidades.nif','entidades.emailadm')
            ->where('numfactura','<>','')
            ->when($this->filtroenviada!='', function ($query){
                $query->where('enviada',$this->filtroenviada);
                })
            ->when($this->filtropagada!='', function ($query){
                $query->where('pagada',$this->filtropagada);
                })
            ->when($this->entidad->id!='', function ($query){
                $query->where('entidad_id',$this->entidad->id);
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
        return $this->rowsQuery->paginate();
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
            if ($zip->open(public_path($fileName), ZipArchive::CREATE) === true) {
                $files = File::files(public_path($ruta));

                foreach ($files as $key => $value) {
                    $relativeNameInZipFile = basename($value);
                    $zip->addFile($value, $relativeNameInZipFile);
                }

                $zip->close();
            }
            return response()->download(public_path($fileName));
        }
    }

    public function mailSelected(){
        $conproblemas=0;
        $facturas = $this->rows;
        $a=substr($this->filtroanyo, -2);
        $m=substr($this->filtromes+100, -2);
        $ruta='storage/facturas/'.$a.'/'.$m.'/';
        if (!file_exists($ruta)) {
            $message="La ruta no existe";
            session()->flash('message', 'No existe este directorio.');
        }

        foreach ($facturas as $factura) {
            $fileName=$ruta.'Fra_Suma_'.$factura->serie.'_'.substr ( $factura->numfactura ,-5 ).'_'.substr ( $factura->entidad ,0,10 ).'.pdf' ;

            if (!file_exists($fileName) || !$factura->mail) {
                $conproblemas++;
            }else{
                Mail::to($factura->mail)
                    ->bcc('alex.arregui@hotmail.es')
                    ->send(new MailFactura($factura));
                    // ->queue(new MailFactura($factura);
            }
        }
        $this->dispatchBrowserEvent('notify', 'Mails enviados!. Con problemas:'. $conproblemas);
    }


    public function exportSelected(){
        //toCsv es una macro a n AppServiceProvider
        return response()->streamDownload(function(){
            echo $this->selectedRowsQuery->toCsv();
        },'prefacturas.csv');

        $this->dispatchBrowserEvent('notify', 'CSV Facturas descargado!');
    }


    public function render()
    {
        if($this->selectAll) $this->selectPageRows();
        $facturaciones = $this->rows;


        $totales= Facturacion::query()
        ->join('entidades','facturacion.entidad_id','=','entidades.id')
        ->join('facturacion_detalles','facturacion.id','=','facturacion_detalles.facturacion_id')
        ->select('facturacion.*', 'entidades.entidad', 'entidades.nif','entidades.emailadm',DB::raw('sum(unidades * coste) as totalbase'),DB::raw('sum(unidades * coste * iva) as totaliva'),DB::raw('sum(unidades * coste * (1+ iva)) as totales'))
        ->where('numfactura','<>','')
        ->when($this->filtroenviada!='', function ($query){
            $query->where('enviada',$this->filtroenviada);
            })
        ->when($this->filtropagada!='', function ($query){
            $query->where('pagada',$this->filtropagada);
            })
        ->when($this->filtrofacturado!='', function ($query){
            if($this->filtrofacturado=='0'){
                $query->where('asiento','0');
            }else{
                $query->where('asiento','>','0');
            }
        })
        ->searchYear('fechafactura',$this->filtroanyo)
        ->searchMes('fechafactura',$this->filtromes)
        ->search('entidades.entidad',$this->search)
        ->orSearch('facturacion.numfactura',$this->search)
        ->first();


        return view('livewire.facturaciones',compact('facturaciones','totales'));
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
