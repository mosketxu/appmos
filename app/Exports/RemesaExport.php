<?php

namespace App\Exports;

use App\Models\Facturacion;
use Maatwebsite\Excel\Concerns\FromCollection;

class RemesaExport implements FromCollection
{

    public $remesa;


    function __construct($remesa){
        $this->remesa=$remesa;
    }
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return $this->remesa;
    }
}
