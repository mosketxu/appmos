<?php

namespace App\Exports;

use App\Models\Facturacion;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class FacturacionExport implements FromCollection,WithHeadings{

    public $facturacion;

    function __construct($facturacion){
        $this->facturacion=$facturacion;
    }

    public function headings(): array{
        return [
            ['Serie','Factura','Fecha','FechaOperacion','CodigoCuenta','CIFEUROPEO','Cliente','Comentario SII','Contrapartida','CodigoTransaccion','ClaveOperaciónFact'
            ,'Importe Factura','Base Imponible1','%Iva1','Cuota Iva1',
            '%RecEq1','Cuota Rec1','CodigoRetencion','Base Ret','%Retención','Cuota Retención','Base Imponible2','%Iva2','Cuota Iva2','%RecEq2','Cuota Rec2','BaseImponible3','%Iva3',
            'Cuota Iva3','%RecEq3','Cuota Rec3']
        ];
    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return $this->facturacion;
    }
}
