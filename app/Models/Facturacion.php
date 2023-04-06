<?php

namespace App\Models;

use App\Actions\FacturaImprimirAction;
use App\Http\Livewire\Facturacion\FacturaDetalle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

// use Illuminate\Database\Eloquent\SoftDeletes;

class Facturacion extends Model
{
    use HasFactory;
    // use SoftDeletes;

    protected $table = 'facturacion';

    protected $dates = ['deleted_at'];

    // protected $dates = ['fechafactura','fechavencimiento'];
    protected $casts = [
        'fechafactura' => 'date:Y-m-d',
        'fechavencimiento' => 'date:Y-m-d',
    ];

    protected $fillable=['numfactura','serie','entidad_id','ciclo_id','totaliva','base','exenta','total','fechafactura','fechavencimiento','metodopago_id','refcliente','mail',
    'facturada','enviar','enviada','pagada','facturable','asiento','fechaasiento','observaciones','notas','ruta','fichero'];

    // protected $appends = ['diez','veinticinco','otros'];

    public function metodopago(){return $this->belongsTo(MetodoPago::class);}
    public function facturadetalles(){return $this->hasMany(FacturacionDetalle::class)->orderBy('tipo')->orderBy('orden');}
    public function entidad(){return $this->belongsTo(Entidad::class);}
    public function ciclo(){return $this->belongsTo(Ciclo::class);}

    public function conceptos(){
        return $this->hasManyThrough(
            FacturacionDetalleConcepto::class,
            FacturacionDetalle::class,
            'facturacion_id',
            'facturaciondetalle_id',
            'id',
            'id');
    }

    public function getFraAttribute(){return 'Fra. '. $this->numfactura . ' Suma';}
    public function getDateFraAttribute(){if ($this->fechafactura) {return $this->fechafactura->format('d/m/Y');}}
    public function getDateVtoAttribute(){if ($this->fechavencimiento) {return $this->fechavencimiento->format('d/m/Y');}}
    public function getEnviarEstAttribute(){return ['0'=>['red','No'],'1'=>['green','Sí']][$this->enviar] ?? ['gray',''];}
    public function getEnviadaEstAttribute(){return ['0'=>['red','No'],'1'=>['green','Sí']][$this->enviada] ?? ['gray',''];}
    public function getPagadaEstAttribute(){return ['0'=>['red','No'],'1'=>['green','Sí']][$this->pagada] ?? ['gray',''];}
    public function getFacturableEstAttribute(){return ['0'=>['red','No'],'1'=>['green','Sí']][$this->facturable] ?? ['gray',''];}
    public function getContabilizadaAttribute(){if ($this->asiento){return ['green','Sí'];}else{return ['red','No'];}}
    public function getFacturadoAttribute(){if ($this->numfactura){return ['green','Sí'];}else{return ['red','No'];}}
    public function getFacturadaEstAttribute(){if ($this->asiento){return ['green','Sí'];}else{return ['red','No'];}}

    public function getRutaficheroAttribute(){return $this->ruta.'/'.$this->fichero;}
    public function getFactura5Attribute(){return $this->serie.'_'.substr($this->numfactura,-5);}

    public function scopeFacturas(Builder $query, $filtroenviada, $filtropagada, $filtrofacturado,$filtroanyo,$filtromes ,$search) : Builder{
        return $query->join('entidades','facturacion.entidad_id','=','entidades.id')
            ->join('facturacion_detalles','facturacion.id','=','facturacion_detalles.facturacion_id')
            // ->select('facturacion.*', 'entidades.entidad', 'entidades.nif','entidades.emailadm',
            //     DB::raw('sum(unidades * importe) as totalbase'),
            //     DB::raw('sum(unidades * importe * iva) as totaliva'),
            //     DB::raw('sum(unidades * importe * (1+ iva)) as totales'))
            ->select('facturacion.*', 'entidades.entidad', 'entidades.nif','entidades.emailadm')
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

    public static function pdffactura($f){
        $fac=new FacturaImprimirAction;
        $fac->execute($f);
    }

    public function getDiezAttribute(){
        $fechav=$this->fechavencimiento->format('d');
        if ($fechav=="10") {
            $fd=FacturacionDetalle::select('id')->where('facturacion_id', $this->id)->get();
            $fd=$fd->toArray();
            $fdc=FacturacionDetalleConcepto::whereIn('facturaciondetalle_id', $fd)->get();
            return $fdc->sum('total');
        }else{
            return "0";
        }
    }

    public function getVeinticincoAttribute(){
        if ($this->fechavencimiento->format('d')=="25") {
            $fd=FacturacionDetalle::select('id')->where('facturacion_id', $this->id)->get();
            $fd=$fd->toArray();
            $fdc=FacturacionDetalleConcepto::whereIn('facturaciondetalle_id', $fd)->get();
            return $fdc->sum('total');
        }else{
            return "0";
        }
    }

    public function getOtrosAttribute(){
        $fechav=$this->fechavencimiento->format('d');
        if ($fechav!="25" && $fechav!="10") {
            $fd=FacturacionDetalle::select('id')->where('facturacion_id', $this->id)->get();
            $fd=$fd->toArray();
            $fdc=FacturacionDetalleConcepto::whereIn('facturaciondetalle_id', $fd)->get();
            return $fdc->sum('total');
        }else{
            return "0";
        }
    }

    public function getTotalesAttribute(){
        $fd=FacturacionDetalle::select('id')->where('facturacion_id', $this->id)->get();
        $fd=$fd->toArray();
        $fdc=FacturacionDetalleConcepto::whereIn('facturaciondetalle_id',$fd)->get();
        $base0=$fdc->where('iva','0.00')->sum('base');
        $base4=$fdc->where('iva','0.04')->sum('base');
        $iva4=$fdc->where('iva','0.04')->sum('totaliva');
        $base10=$fdc->where('iva','0.10')->sum('base');
        $iva10=$fdc->where('iva','0.10')->sum('totaliva');
        $base21=$fdc->where('iva','0.21')->sum('base');
        $iva21=$fdc->where('iva','0.21')->sum('totaliva');
        $exenta=$fdc->where('tipo','<>','1')->sum('exenta');
        $suplido=$fdc->where('tipo','1')->sum('exenta');
        $totaliva=$fdc->sum('totaliva');
        $totalbase=$base0+$base4+$base10+$base21;

        $total=$fdc->sum('total');
        $a= [
            '0'=>[$base0,''],
            '4'=>[$base4,$iva4],
            '10'=>[$base10,$iva10],
            '21'=>[$base21,$iva21],
            's'=>[$suplido,''],
            'e'=>[$exenta,''],
            't'=>[$totalbase,$total,$totaliva],
        ];

        return $a;

    }
}
