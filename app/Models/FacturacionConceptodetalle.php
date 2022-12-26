<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FacturacionConceptodetalle extends Model
{
    use HasFactory;

    protected $table = 'facturacion_conceptodetalles';

    protected $fillable = ['concepto','facturacionconcepto_id','orden','unidades','importe'];

    public function concepto()
    {
        return $this->belongsTo(FacturacionConcepto::class);
    }


}
