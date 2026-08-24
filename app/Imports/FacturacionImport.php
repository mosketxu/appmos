<?php

namespace App\Imports;

use App\Models\Facturacion;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\WithValidation;

// use Maatwebsite\Excel\Concerns\WithStartRow;


class FacturacionImport implements ToModel
{
    use importable;
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row): \App\Models\Facturacion
    {
        // dd($row[4]);
        return new Facturacion([
            'id'=>$row[0],
            'entidad_id'=>$row[1],
            'serie'=>$row[2],
            'numfactura'=>$row[3],
            'fechafactura'=>$row[4],
            'fechavencimiento'=>$row[5],
            'metodopago_id'=>$row[6],
            'refcliente'=>$row[7],
            'mail'=>$row[8],
            'enviar'=>$row[9],
            'enviada'=>$row[10],
            'pagada'=>$row[11],
            'facturada'=>$row[12],
            'facturable'=>$row[13],
            'asiento'=>$row[14],
            'fechaasiento'=>$row[15],
            // 'observaciones'=>$row[16],
            // 'notas'=>$row[17],
            // 'ruta'=>$row[18],
            // 'fichero'=>$row[19],
         ]);
    }


}
