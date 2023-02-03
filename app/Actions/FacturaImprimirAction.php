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

        $base4=$factura->totales[4][0];
        $base10=$factura->totales[10][0];
        $base21=$factura->totales[21][0];
        $base=$factura->totales['t'][0];
        $exenta=$factura->totales['e'][0];
        $suplidos=$factura->totales['s'][0];
        $totaliva=$factura->totales['t'][2];
        $total=$factura->totales['t'][1];

        $pdf = new Dompdf();
        $pdf = \PDF::loadView('facturacion.facturapdf', compact('factura','facturadetalles','base','exenta','suplidos','totaliva','total'));


        Storage::put('public/'.$factura->ruta.'/'.$factura->fichero, $pdf->output());



        // $pdf = \PDF::loadView('facturacion.facturapdf', compact('factura','facturadetalles','base','suplidos','totaliva','total'));
        // $pdf->setPaper('a4','portrait');
        // return $pdf->stream('factura_'.$factura->numfactura.'.pdf'); //asi lo muestra por pantalla



        //     return $pdf->download($factura->fichero);

        // return $f;
    }
}
