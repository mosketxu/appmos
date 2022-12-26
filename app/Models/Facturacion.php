<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

use Illuminate\Database\Eloquent\SoftDeletes;

class Facturacion extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'facturacion';

    protected $dates = ['deleted_at'];

    // protected $dates = ['fechafactura','fechavencimiento'];
    protected $casts = [
        'fechafactura' => 'date:Y-m-d',
        'fechavencimiento' => 'date:Y-m-d',
    ];

    protected $fillable=['numfactura','serie','entidad_id','ciclo_id','fechafactura','fechavencimiento','metodopago_id','refcliente','mail',
    'facturada','enviar','enviada','pagada','facturable','asiento','fechaasiento','observaciones','notas','ruta','fichero'];

    public function metodopago(){return $this->belongsTo(MetodoPago::class);}
    public function facturadetalles(){return $this->hasMany(FacturacionDetalle::class)->orderBy('tipo')->orderBy('orden');}
    public function entidad(){return $this->belongsTo(Entidad::class);}
    public function ciclo(){return $this->belongsTo(Ciclo::class);}

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

    public function getFacturadaEstAttribute()
    {
        if ($this->asiento){
            return ['green','Sí'];
        }else{
            return ['red','No'];
        }
    }

    public function getRutaficheroAttribute()
    {
        return $this->ruta.'/'.$this->fichero;
    }

    public function getFactura5Attribute()
    {
        return $this->serie.'_'.substr($this->numfactura,-5);
    }

    public function scopeFacturas(Builder $query, $filtroenviada, $filtropagada, $filtrofacturado,$filtroanyo,$filtromes ,$search) : Builder
    {
        return $query->join('entidades','facturacion.entidad_id','=','entidades.id')
            ->join('facturacion_detalles','facturacion.id','=','facturacion_detalles.facturacion_id')
            ->select('facturacion.*', 'entidades.entidad', 'entidades.nif','entidades.emailadm',
                DB::raw('sum(unidades * coste) as totalbase'),
                DB::raw('sum(unidades * coste * iva) as totaliva'),
                DB::raw('sum(unidades * coste * (1+ iva)) as totales'))
            ->where('numfactura','<>','')
            ->when($this->filtroenviada!='', function ($query){
                $query->where('enviada',$this->filtroenviada);
                })
            ->when($this->filtropagada!='', function ($query){
                $query->where('pagada',$this->filtropagada);
                })
            ->when($this->filtrofacturado!='', function ($query){
                if($this->filtrofacturado=='0'){
                    $query->where('asiento','0');
                }else{
                    $query->where('asiento','>','0');
                }
            })
            ->searchYear('fechafactura',$this->filtroanyo)
            ->searchMes('fechafactura',$this->filtromes)
            ->search('entidades.entidad',$this->search)
            ->orSearch('facturacion.numfactura',$this->search);
    }
}
