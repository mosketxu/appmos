<?php

namespace App\Actions;

use App\Models\Facturacion;
use App\Models\FacturacionDetalle;
use App\Models\FacturacionDetalleConcepto;
use Illuminate\Support\Facades\DB;

class FacturaReplicarAction
{
    public function execute(Facturacion $factura)
    {
        return DB::transaction(function () use ($factura) {
            $clone = $factura->replicate()->fill([
                'numfactura'=>null,
                'enviada'=>0,
                'pagada'=>0,
                'facturada'=>0,
                'asiento'=>0,
                'fechaasiento'=>null,
                'observaciones'=>null,
                'notas'=>null,
                'ruta'=>null,
                'fichero'=>null,
            ]);
            $clone->save();

            $detalles=FacturacionDetalle::with('facturadetalleconceptos')
                ->where('facturacion_id', $factura->id)
                ->get();
            foreach ($detalles as $detalle) {
                $detalleClone=$detalle->replicate()->fill([
                    'facturacion_id'=>$clone->id,
                ]);
                $detalleClone->save();

                foreach ($detalle->facturadetalleconceptos as $concepto) {
                    $conceptoClone=$concepto->replicate()->fill([
                        'facturaciondetalle_id'=>$detalleClone->id,
                    ]);
                    $conceptoClone->save();
                }
            }

            return $clone;
        });
    }
}
