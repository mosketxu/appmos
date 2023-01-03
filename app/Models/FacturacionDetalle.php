<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FacturacionDetalle extends Model
{
    use HasFactory;

    protected $fillable=['facturacion_id','orden','tipo','concepto','unidades','importe','iva','totaliva','base','exenta','total','subcuenta','pagadopor'];

    const TIPOS =[
        '0'=>'General',
        '1'=>'Suplido',
        '2'=>'Otros',
    ];

    public function factura(){return $this->belongsTo(Facturacion::class);}
    public function facturadetalleconceptos(){return $this->hasMany(FacturacionDetalleConcepto::class,'facturaciondetalle_id','id');}

    public function getTipofraAttribute(){
        return [
            '0'=>'Gral',
            '1'=>'Suplido',
            '2'=>'Otros'
        ][$this->tipo] ?? '0';
    }

    // public function getBaseAttribute(){
    //     dd($this->iva);
    //     return $this->iva!=0 ? round($this->unidades*$this->coste,2) : 0;

    //     return round($this->base,2);
    // }
    // public function getExentaAttribute(){return round($this->exenta,2);}
    // public function getTotalivaAttribute(){return round($this->totaliva,2);}
    // public function getTotalAttribute(){return round($this->total,2);}

    public function getPorAttribute(){
        if($this->pagadopor=='1')
            return 'Marta';
        elseif($this->pagadopor=='2')
            return 'Susana';
        else
            return 'NP';
    }


}
