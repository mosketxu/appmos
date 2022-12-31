<?php

namespace App\Actions;

use App\Http\Livewire\Factura;
use App\Models\Facturacion;
use App\Models\FacturacionDetalle;
use App\Models\FacturacionDetalleConcepto;

class FacturaConceptoStoreAction
{
    public function execute($factura,$concepto)
    {
        $sumaId=!$factura->entidad->suma_id ? '1' :$factura->entidad->suma_id;
        if($concepto->ciclocorrespondiente!='2')
        {
            if ($concepto->ciclo_id==1) {
                $per=mes($factura->fechafactura,$concepto->ciclocorrespondiente,$factura->entidad->idioma);
            }else{
                $per=trimestre($factura->fechafactura,$concepto->ciclocorrespondiente,$factura->entidad->idioma);
            }
        }
        $f=FacturacionDetalle::create([
            'facturacion_id'=>$factura->id,
            'orden'=>'0',
            'tipo'=>'0',
            'concepto'=>$concepto->concepto . ' ' . $per,
            'unidades'=>'1',
            'importe'=>$concepto->importe,
            'iva'=>$factura->entidad->tipoiva,
            'totaliva'=>'0',
            'base'=>'0',
            'exenta'=>'0',
            'total'=>'0',
            'subcuenta'=>'705000',
            'pagadopor'=>$sumaId,
        ]);

        $facdetalles=$concepto->detalles;
        foreach ($facdetalles as $fd) {
            $fdet=FacturacionDetalleConcepto::create([
                'facturaciondetalle_id'=>$f->id,
                'orden'=>$fd->id,
                'tipo'=>'0',
                'concepto'=>$fd->concepto  . ' ' . $per,
                'unidades'=>$fd->unidades,
                'importe'=>$fd->importe,
                'iva'=>$factura->entidad->tipoiva,
                'totaliva'=>round($fd->unidades * $fd->importe * $factura->entidad->tipoiva,2 ),
                'base'=>$factura->entidad->tipoiva != '0' ? round($fd->unidades * $fd->importe ,2 ) : '0',
                'exenta'=>$factura->entidad->tipoiva == '0' ? round($fd->unidades * $fd->importe ,2 ) : '0',
                'total'=>round($fd->unidades * $fd->importe * (1+$factura->entidad->tipoiva),2 ),
                'subcuenta' =>'705000',
                'bloqueado'=>'0'
            ]);

        }

        Facturacion::actualizaimportes($factura->id);

        return $f;
    }
}
