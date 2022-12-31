<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FacturacionConcepto extends Model
{
    use HasFactory;

    protected $table = 'facturacion_conceptos';

    // protected $fillable = ['entidad_id','concepto','importe','ciclo_id','ciclocorrespondiente','agrupacion'];
    protected $fillable = ['entidad_id','ciclo_id','ciclocorrespondiente','concepto'];
    public function entidad(){return $this->belongsTo(Entidad::class);}
    public function ciclo(){return $this->belongsTo(Ciclo::class);}
    public function detalles(){return $this->hasMany(FacturacionConceptodetalle::class,'facturacionconcepto_id')->orderBy('orden');}

    public function getCorrespondeAttribute()
    {
        if($this->ciclocorrespondiente=='1')
            return 'Ant';
        elseif($this->ciclocorrespondiente=='0')
            return 'Cor';
        else
            return 'Ning';
    }
}
