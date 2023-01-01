<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FacturacionDetalleConcepto extends Model
{
    use HasFactory;


    protected $fillable=['facturaciondetalle_id','orden','tipo','concepto','unidades','importe','iva','totaliva','base','exenta','total','subcuenta','bloqueado'];

    const TIPOS =[
        '0'=>'General',
        '1'=>'Suplido',
        '2'=>'Otros',
    ];

    public function detalle(){return $this->belongsTo(FacturacionDetalle::class);}

    public function calculo(){
        $this->totaliva=round($this->unidades*$this->importe*$this->iva,2);
        $this->total=round($this->unidades*$this->importe*(1+$this->iva),2);
        $this->base=$this->iva!='0.00' ? round($this->unidades*$this->importe,2) : '0.00';
        $this->exenta=$this->iva=='0.00' ? round($this->unidades*$this->importe,2) : '0.00';
        $this->save();
    }
}
