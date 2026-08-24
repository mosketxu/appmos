<?php

namespace App\Exports;

use App\Models\Facturacion;
use Illuminate\Support\Enumerable;
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
    public function collection(): Enumerable
    {
        return $this->remesa;
    }
}
