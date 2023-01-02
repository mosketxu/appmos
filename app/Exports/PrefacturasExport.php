<?php

namespace App\Exports;

use App\Models\Facturacion;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PrefacturasExport implements FromCollection,WithHeadings{


    public $prefacturas;

    function __construct($prefacturas){
        // dd($prefacturas);
        $this->prefacturas=$prefacturas;
    }

    public function headings(): array{
    return [
        ['Empresa','Fecha Factura','Fecha Vencimiento','Concepto','Base','Exento','Iva','Total']
    ];
}

    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return $this->prefacturas;
    }
}
