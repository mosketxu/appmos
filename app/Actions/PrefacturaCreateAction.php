<?php

namespace App\Actions;

use App\Models\{Entidad, Facturacion,FacturacionConcepto, FacturacionConceptodetalle, FacturacionDetalle};
use Illuminate\Support\Facades\DB;

class PrefacturaCreateAction
{
    public function execute(FacturacionConcepto $concepto, Entidad $entidad, $anyoplan)
    {
        return DB::transaction(function () use ($concepto, $entidad, $anyoplan) {
            $ciclos=$concepto->ciclo->ciclos;

        for ($i=0; $i < $ciclos ; $i++) {

            $mes=$concepto->ciclo_id!='3' ? $i : $i*3;
            $diaF=($entidad->diafactura >'28') ? $this->diaultimo($entidad->diafactura,$mes+1,$anyoplan) : $entidad->diafactura;
            $diaV=($entidad->diavencimiento >'28') ? $this->diaultimo($entidad->diavencimiento,$mes+1,$anyoplan) : $entidad->diavencimiento;
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
            $fac->setRelation('entidad', $entidad);

            $fc=new FacturaConceptoStoreAction;
            $fc->execute($fac,$concepto);

        }
            return 'Exito';
        });
    }

    public function diaultimo($dia,$mes,$anyo){
        $ultimoDiaDelMes = cal_days_in_month(CAL_GREGORIAN, (int) $mes, (int) $anyo);

        return (int) $dia > $ultimoDiaDelMes ? (string) $ultimoDiaDelMes : $dia;
    }
}
