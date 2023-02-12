<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class FacturacionControlExport implements FromCollection,WithHeadings{


    public $control;

    function __construct($control){
        $this->control=$control;
    }

    public function headings(): array{
        return [
            ['Cuenta','Cliente','Base','Otros','Suplidos','Iva','Total','Fra.','Fecha Vencimiento','Pago'
            ,'Marta','Susana']
        ];
    }

    public function collection()
    {
        return $this->control;
    }
}
