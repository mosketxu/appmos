<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Entidad extends Model
{
    use HasFactory;
    use \App\Models\Concerns\SoloEntidadesPermitidas;
    use HasFactory;
    protected $table = 'entidades';
    /** Una entidad nueva NO recibe facturas por correo hasta que se marque «Enviar» (antes la columna tenía por defecto true). */
    protected $attributes = ['enviar' => false];

    protected $fillable=['entidad','alias','favorito',
                        'entidadtipo_id','direccion','codpostal',
                        'localidad','provincia_id','pais_id',
                        'nif','tfno','emailgral','emailadm',
                        'web','idioma',
                        'banco1','iban1',
                        'banco2','iban2',
                        'banco3','iban3',
                        'periodoimpuesto_id','metodopago_id','ciclofacturacion_id','importe_facturacion','periodo_facturacion','cicloimpuesto_id','contabilidad_analitica',
                        'diafactura','diavencimiento','referenciacliente',
                        'tipoiva','porcentajemarta','porcentajesusana',
                        'cuentacontable','codigo_cliente','cnae','epigrafe_iae','observaciones','mail_peticion_check','mail_peticion','mail_peticion_asunto','mail_peticion_cc',
                        'suma_id','suma_id','cliente','proveedor','contacto',
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

    public function pais(){return $this->belongsTo(Pais::class);}
    public function provincia(){return $this->belongsTo(Provincia::class);}
    public function metodopago(){return $this->belongsTo(MetodoPago::class);}
    public function suma(){return $this->belongsTo(Suma::class);}
    public function entidadtipo(){return $this->belongsTo(EntidadTipo::class);}
    public function contactos(){return $this->hasMany(ContactoEntidad::class);}
    public function cicloimp(){return $this->belongsTo(Ciclo::class, 'cicloimpuesto_id','id');}
    public function ciclofac(){return $this->belongsTo(Ciclo::class, 'ciclofacturacion_id','id');}
    public function conceptos(){return $this->hasMany(FacturacionConcepto::class);}
    public function mailsEnviados(){return $this->hasMany(MailEnviado::class);}
    public function historico(){return $this->hasMany(EntidadHistorico::class)->orderByDesc('fecha')->orderByDesc('id');}

    /** Cambia el estado y deja constancia en el historial (fecha y motivo). */
    public function cambiarEstado(int $nuevo, ?string $fecha = null, ?string $motivo = null): void
    {
        $antes = $this->estado === null ? null : (int) $this->estado;
        $this->estado = $nuevo;
        $this->save();
        if ($antes !== $nuevo) {
            $this->registrarEstado($antes, $nuevo, $fecha, $motivo);
        }
    }

    /** Solo la línea del historial (cuando el estado ya se ha guardado por otro camino). */
    public function registrarEstado(?int $antes, int $nuevo, ?string $fecha = null, ?string $motivo = null): void
    {
        EntidadHistorico::create([
            'entidad_id' => $this->id, 'tipo' => 'estado', 'estado_anterior' => $antes, 'estado_nuevo' => $nuevo,
            'fecha' => $fecha ?: now()->toDateString(), 'comentario' => trim((string) $motivo) ?: null, 'user_id' => auth()->id(),
        ]);
    }

    /** Estados de la entidad (columna estado): 1 activo, 0 baja, 2 inactivo, 3 liquidada. Mismos que la columna Estado del Excel de destinatarios. */
    public const ESTADOS = [1 => 'Activo', 0 => 'Baja', 2 => 'Inactivo', 3 => 'Liquidada'];
    /** Orden en que pasa con cada clic: activo → baja → inactivo → liquidada → activo. */
    public const ESTADO_SIGUIENTE = [1 => 0, 0 => 2, 2 => 3, 3 => 1];

    public function getStatusColorAttribute(){return ['0'=>['red','Baja'],'1'=>['green','Activo'],'2'=>['yellow','Inactivo'],'3'=>['gray','Liquidada']][$this->estado] ?? ['gray',''];}
    public function getFacColorAttribute(){return ['0'=>['red','Baja'],'1'=>['green','Activo']][$this->facturar] ?? ['gray',''];}
    public function getFavColorAttribute(){return ['0'=>['gray','x2606'],'1'=>['yellow','x2605']][$this->favorito] ?? 'gray';}
    public function getDateForHumansAttribute(){if ($this->created_at) {return $this->created_at->format('d/m/Y');}}

}
