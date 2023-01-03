<?php

namespace App\Actions;

use App\Models\Facturacion;
use App\Models\FacturacionDetalle;
use App\Models\FacturacionDetalleConcepto;
use Illuminate\Support\Facades\Storage;
use Dompdf\Dompdf;


class FacturaImprimirAction
{

    public function execute($facturacion){
        $factura=Facturacion::with('entidad')->find($facturacion->id);

        $a=FacturacionDetalle::select('id')->where('facturacion_id', $facturacion->id)->orderBy('orden')->get();
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


        Storage::put('public/'.$factura->ruta.'/'.$factura->fichero, $pdf->output());

        dd('sdfs');

        // $pdf = \PDF::loadView('facturacion.facturapdf', compact('factura','facturadetalles','base','suplidos','totaliva','total'));
        // $pdf->setPaper('a4','portrait');
        // return $pdf->stream('factura_'.$factura->numfactura.'.pdf'); //asi lo muestra por pantalla



        //     return $pdf->download($factura->fichero);

        // return $f;
    }
}
