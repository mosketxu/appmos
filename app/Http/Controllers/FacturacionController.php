<?php

namespace App\Http\Controllers;

use App\Imports\FacturacionImport;
use App\Models\{Facturacion, Entidad, FacturacionDetalle, FacturacionDetalleConcepto};
use ZipArchive;
use File;
use Excel;
use Dompdf\Dompdf;

class FacturacionController extends Controller
{

    public function index(){
        return view('facturacion.index');
    }

    public function create(){
        return view('facturacion.create');
    }

    public function createprefactura(Entidad $entidad){
    return view('facturacion.createprefactura',compact('entidad'));
    }

    public function show($entidadId){
        $entidad=Entidad::find($entidadId);
        return view('facturacion.entidad',compact(['entidad']));
    }

    public function prefacturasentidad($entidadId){
        $entidad=Entidad::find($entidadId);
        $ruta="facturacion.prefacturasentidad";
        return view('facturacion.prefacturasentidad',compact(['entidad','ruta']));
    }

    public function editprefactura($facturacionId){
        $ruta="facturacion.prefacturasentidad";
        $facturacion=Facturacion::find($facturacionId);
        return view('facturacion.editprefactura',compact('facturacion','ruta'));
    }

    public function edit(Facturacion $facturacion){
        return view('facturacion.edit',compact('facturacion'));
    }

    public function prefacturas(){
        $ruta="facturacion.prefacturas";
        return view('facturacion.prefacturas',compact('ruta'));
    }

    public function pdffactura($facturaid){

        $factura=Facturacion::with('entidad')->find($facturaid);
        $a=FacturacionDetalle::select('id')->where('facturacion_id', $facturaid)->orderBy('orden')->get();
        $a=$a->toArray();
        $facturadetalles=FacturacionDetalleConcepto::whereIn('facturaciondetalle_id',$a)->get();

        $base4=$facturadetalles->where('iva','0.04')->sum('base');
        $base10=$facturadetalles->where('iva','0.10')->sum('base');
        $base21=$facturadetalles->where('iva','0.21')->sum('base');
        $base=$base4 + $base10 +$base21;
        $exenta=$facturadetalles->where('tipo'!='1')->sum('exenta');
        $suplidos=$facturadetalles->where('tipo'=='1')->sum('exenta');
        $totaliva=$facturadetalles->sum('totaliva');
        $total=$facturadetalles->sum('total');

        $pdf = new Dompdf();

        $pdf = \PDF::loadView('facturacion.facturapdf', compact('factura','facturadetalles','base','suplidos','totaliva','total'));
        $pdf->setPaper('a4','portrait');
        return $pdf->stream('factura_'.$factura->numfactura.'.pdf'); //asi lo muestra por pantalla


    }


    public function downfacturas(){
        $facturas=Facturacion::get();

        foreach ($facturas as $factura) {
            $this->downfacturapdf($factura);
        }

        $this->downloadZip();
    }

    public function downloadZip(){
        $zip = new ZipArchive;
        $fileName = 'myNewFile.zip';
        $ruta='storage/facturas/21/06/';
        if ($zip->open(public_path($fileName), ZipArchive::CREATE) === TRUE)
        {
            $files = File::files(public_path($ruta));
            foreach ($files as $key => $value) {
                $relativeNameInZipFile = basename($value);
                $zip->addFile($value, $relativeNameInZipFile);
            }
            $zip->close();
        }
        return response()->download(public_path($fileName));
    }

    public function import(){

        Excel::import(new FacturacionImport, 'Facturas.xlsx');

        return redirect('/')->with('success', 'All good!');
    }

}
