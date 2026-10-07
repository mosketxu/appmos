<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Estado de un periodo de una obligación: no | pendiente | revision | revisado | presentado. */
class ImpuestoEstado extends Model
{
    protected $table = 'impuesto_estados';
    protected $fillable = ['entidad_impuesto_id', 'ejercicio', 'periodo', 'estado', 'user_id'];
}
