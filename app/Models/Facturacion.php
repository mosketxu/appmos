<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Facturacion extends Model
{
    use HasFactory;

    protected $table = 'facturacion';

    protected $dates = ['fechafactura','fechavencimiento'];

    protected $fillable=['numfactura','entidad_id','fechafactura','fechavencimiento','metodopago_id','refcliente','mail',
    'enviar','enviada','pagada','facturable','asiento','fechaasiento','observaciones','notas'];

    public function metodopago()
    {
        return $this->belongsTo(MetodoPago::class);
    }

    public function facturadetalles()
    {
        return $this->hasMany(FacturacionDetalle::class)->orderBy('tipo')->orderBy('orden');
    }

    public function entidad()
    {
        return $this->belongsTo(Entidad::class);
    }

    public function getDateFraAttribute()
    {
        if ($this->fechafactura) {
            return $this->fechafactura->format('d/m/Y');
        }
    }

    public function getDateVtoAttribute()
    {
        if ($this->fechavencimiento) {
            return $this->fechavencimiento->format('d/m/Y');
        }
    }

    public function getEnviarEstAttribute()
    {
        return [
            '0'=>['red','No'],
            '1'=>['green','Sí']
        ][$this->enviar] ?? ['gray',''];
    }

    public function getEnviadaEstAttribute()
    {
        return [
            '0'=>['red','No'],
            '1'=>['green','Sí']
        ][$this->enviada] ?? ['gray',''];
    }

    public function getPagadaEstAttribute()
    {
        return [
            '0'=>['red','No'],
            '1'=>['green','Sí']
        ][$this->pagada] ?? ['gray',''];
    }

    public function getFacturableEstAttribute()
    {
        return [
            '0'=>['red','No'],
            '1'=>['green','Sí']
        ][$this->facturable] ?? ['gray',''];
    }

    public function getContabilizadaAttribute()
    {
        if ($this->asiento){
            return ['green','Sí'];
        }else{
            return ['red','No'];
        }
    }

    public function getFacturadoAttribute()
    {
        if ($this->numfactura){
            return ['green','Sí'];
        }else{
            return ['red','No'];
        }
    }

}
