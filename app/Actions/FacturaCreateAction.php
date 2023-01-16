<?php

namespace App\Actions;

use App\Models\Facturacion;
use App\Models\FacturacionDetalle;

class FacturaCreateAction
{
    public function execute(Facturacion $factura){


        $serie= !$factura->serie ? substr($factura->fechafactura->format('Y'), -2) : $factura->serie;
        $factura->metodopago_id= !$factura->metodopago_id ? '1' : $factura->metodopago_id;


        if (!$factura->numfactura){
            $fac=Facturacion::where('serie', $serie)->max('numfactura') ;
            $fac= $fac ? $fac + 1 : ($serie * 100000 +1) ;
        }else{
            $fac=$factura->numfactura;
        }
        $ruta='facturas/'.$serie.'/'.$factura->fechafactura->format('m');
        $factura->ruta=$ruta;
        $caracteresmalos=['.',',',"'"];

        $ent=str_replace($caracteresmalos,"",$factura->entidad->entidad);
        $fichero=(trim('Fra_Suma_'.$factura->serie.'_'.substr ( $fac ,-5 ).'_'.$ent,' ').'.pdf');
        $factura->fichero=substr($fichero, 0, 40);
        $factura->serie=$serie;
        $factura->numfactura=$fac;
        $detalles=FacturacionDetalle::where('facturacion_id',$factura->i)->count();
        $factura->facturada=$detalles>0 ? true : false;
        $factura->save();
        return $factura;
    }
}
