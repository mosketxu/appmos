<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactoEntidad extends Model
{
    use HasFactory;
    use \App\Models\Concerns\SoloEntidadesPermitidas;

    protected $table = 'contacto_entidades';

    protected $fillable = ['entidad_id','contacto_id','departamento','comentarios'];

    public function entidad()
    {
        return $this->belongsTo(Entidad::class);
    }

}
