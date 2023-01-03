<?php

namespace App\Http\Controllers;

use App\Imports\FacturacionImport;
use App\Models\{Facturacion, Entidad};
use ZipArchive;
use File;
use Excel;

// use Barryvdh\DomPDF\PDF;

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
        $facturacion=Facturacion::find($facturacionId);
        return view('facturacion.editprefactura',compact('facturacion'));
    }

    public function edit(Facturacion $facturacion){
        return view('facturacion.edit',compact('facturacion'));
    }

    public function prefacturas(){
        $ruta="facturacion.prefacturas";
        return view('facturacion.prefacturas',compact('ruta'));
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
