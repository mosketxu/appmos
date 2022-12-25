<?php

namespace App\Actions;

use App\Models\FacturacionDetalle;


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
        // $per=!$per? 'falla' : $per;
        $f=FacturacionDetalle::create([
            'facturacion_id'=>$factura->id,
            'orden'=>'0',
            'tipo'=>'0',
            'concepto'=>$concepto->concepto . ' ' . $per,
            'unidades'=>'1',
            'coste'=>$concepto->importe,
            'iva'=>$factura->entidad->tipoiva,
            'subcuenta'=>'705000',
            'pagadopor'=>$sumaId,
            ]);
        return $f;
    }

}
