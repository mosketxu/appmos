<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Un impuesto que debe presentar una entidad, con su periodicidad (M, T, A, P). Sin el scope de entidades: la visibilidad la decide App\Support\Impuestos. */
class EntidadImpuesto extends Model
{
    protected $table = 'entidad_impuestos';
    protected $fillable = ['entidad_id', 'modelo_id', 'etiqueta', 'periodicidad', 'user_id', 'observaciones'];

    public function modelo() { return $this->belongsTo(ImpuestoModelo::class, 'modelo_id'); }
    public function estados() { return $this->hasMany(ImpuestoEstado::class, 'entidad_impuesto_id'); }
}
