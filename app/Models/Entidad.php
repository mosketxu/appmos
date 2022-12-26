<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Entidad extends Model
{
    use HasFactory;
    use HasFactory;
    protected $table = 'entidades';
    protected $fillable=['entidad','alias','favorito',
                        'entidadtipo_id','direccion','codpostal',
                        'localidad','provincia_id','pais_id',
                        'nif','tfno','emailgral','emailadm',
                        'web','idioma',
                        'banco1','iban1',
                        'banco2','iban2',
                        'banco3','iban3',
                        'periodoimpuesto_id','metodopago_id','ciclofacturacion_id','cicloimpuesto_id',
                        'diafactura','diavencimiento','referenciacliente',
                        'tipoiva','porcentajemarta','porcentajesusana',
                        'cuentacontable','observaciones',
                        'suma_id','suma_id','cliente',
                        'estado','facturar','enviar','created_at'];

    const STATUSES =[
        '0'=>'Baja',
        '1'=>'Activo',
        '2'=>'N/D',
    ];

    const CICLOS =[
        '0'=>'No Def',
        '1'=>'Mensual',
        '3'=>'Trimestral',
        '12'=>'Anual',
        '13'=>'Mes/Trim',
        '20'=>'Puntual',
        '34'=>'Tri/Cuatri',
    ];

    public function getStatusColorAttribute()
    {
        return [
            '0'=>['red','Baja'],
            '1'=>['green','Activo']
        ][$this->estado] ?? ['gray',''];
    }

    public function getFacColorAttribute()
    {
        return [
            '0'=>['red','Baja'],
            '1'=>['green','Activo']
        ][$this->facturar] ?? ['gray',''];
    }

    public function getFavColorAttribute()
    {
        return [
            '0'=>['gray','x2606'],
            '1'=>['yellow','x2605']
        ][$this->favorito] ?? 'gray';
    }


    public function getDateForHumansAttribute()
    {
        if ($this->created_at) {
            return $this->created_at->format('d/m/Y');
        }
    }

    public function pais()
    {
        return $this->belongsTo(Pais::class);
    }

    public function provincia()
    {
        return $this->belongsTo(Provincia::class);
    }

    public function metodopago()
    {
        return $this->belongsTo(MetodoPago::class);
    }

    public function suma()
    {
        return $this->belongsTo(Suma::class);
    }

    public function entidadtipo()
    {
        return $this->belongsTo(EntidadTipo::class);
    }

    public function contactos()
    {
        return $this->hasMany(ContactoEntidad::class);
    }

    public function cicloimp()
    {
        return $this->belongsTo(Ciclo::class, 'cicloimpuesto_id','id');
    }

    public function ciclofac()
    {
        return $this->belongsTo(Ciclo::class, 'ciclofacturacion_id','id');
    }

    public function conceptos()
    {
        return $this->hasMany(FacturacionConcepto::class);
    }
}
