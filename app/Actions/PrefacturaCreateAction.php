<?php

namespace App\Actions;

use App\Models\{Entidad, Facturacion,FacturacionConcepto, FacturacionConceptodetalle, FacturacionDetalle};

class PrefacturaCreateAction
{
    public function execute(FacturacionConcepto $concepto, Entidad $entidad, $anyoplan)
    {
        $ciclos=$concepto->ciclo->ciclos;

        for ($i=0; $i < $ciclos ; $i++) {

            $mes=$concepto->ciclo_id!='3' ? $i : $i*3;
            $diaF=($entidad->diafactura >'28') ? $this->diaultimo($entidad->diafactura,$mes+1) : $entidad->diafactura;
            $diaV=($entidad->diavencimiento >'28') ? $this->diaultimo($entidad->diavencimiento,$mes+1) : $entidad->diavencimiento;
            // dd($diaF);
            $ffra=$anyoplan.'-'.($mes+1).'-'.$diaF;
            $fvto=$anyoplan.'-'.($mes+1).'-'.$diaV;
            $fac=Facturacion::create([
                'entidad_id'=>$concepto->entidad_id,
                'ciclo_id'=>$concepto->ciclo_id,
                'fechafactura'=>$ffra,
                'fechavencimiento'=>$fvto,
                'metodopago_id'=>$entidad->metodopago_id,
                'refcliente'=>$entidad->refcliente,
                'mail'=>$entidad->emailadm,
                'enviar'=>$entidad->enviar,
                'enviada'=>'0',
                'pagada'=>'0',
                'facturada'=>'0',
                'facturable'=>'1',
                'asiento'=>'0',
                // 'fechaasiento'=>$entidad->fechaasiento,
                'observaciones'=>$entidad->observaciones,
                'notas'=>$entidad->notas,
            ]);

            $fc=new FacturaConceptoStoreAction;
            $fc->execute($fac,$concepto);

        }
    $mensaje='Exito';
        return $mensaje;
    }

    public function diaultimo($dia,$mes){
        // dd()
        $mes31=['1','3','5','7','8','10','12'];
        $mes30=['4','6','9','11'];
        $mes28=['2'];
        if($dia=='31')
            if (in_array($mes, $mes30))
                $dia='30';
            elseif (in_array($mes, $mes28))
                $dia='28';

        if($dia=='30')
            if (in_array($mes, $mes28))
                $dia='28';
        return $dia;
    }
}
