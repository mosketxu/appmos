<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Un impuesto del catálogo (303, 111, 115, 202, 349, 200...). Ver la migración create_impuestos_tables. */
class ImpuestoModelo extends Model
{
    protected $table = 'impuesto_modelos';
    protected $fillable = ['codigo', 'nombre', 'periodicidad', 'desfase', 'mes_anual', 'automatico', 'orden', 'activo'];
    protected $casts = ['automatico' => 'boolean', 'activo' => 'boolean'];

    public function getEtiquetaAttribute(): string
    {
        return ctype_digit($this->codigo) ? 'M'.$this->codigo : $this->codigo;
    }
}
