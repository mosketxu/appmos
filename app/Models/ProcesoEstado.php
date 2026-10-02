<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Estado de un proceso mensual por entidad y periodo (no solicitado / solicitado / recibido). Ver la migración. */
class ProcesoEstado extends Model
{
    protected $table = 'procesos_estado';

    protected $fillable = ['proceso', 'entidad_id', 'periodo', 'estado', 'solicitado_at', 'recibido_at', 'user_id'];

    protected $casts = [
        'solicitado_at' => 'datetime',
        'recibido_at' => 'datetime',
    ];

    public const ESTADOS = ['solicitado' => 'Solicitado', 'recibido' => 'Recibido'];
}
