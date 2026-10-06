<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Una línea del historial de una entidad: cambio de estado (fecha + motivo) o comentario. */
class EntidadHistorico extends Model
{
    protected $table = 'entidad_historico';
    protected $fillable = ['entidad_id', 'fecha', 'tipo', 'estado_anterior', 'estado_nuevo', 'comentario', 'importe_facturacion', 'periodo_facturacion', 'user_id'];
    protected $casts = ['fecha' => 'date'];

    public function entidad() { return $this->belongsTo(Entidad::class); }
    public function user() { return $this->belongsTo(User::class); }
}
