<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Fichero o imagen adjunto a una PU de una entidad (22-nov-2026). */
class PuAdjunto extends Model
{
    protected $table = 'pu_adjuntos';

    protected $fillable = ['pu_id', 'nombre', 'ruta', 'tamano', 'user_id'];

    public const IMAGENES = ['png', 'jpg', 'jpeg', 'gif', 'webp'];

    public function pu()
    {
        return $this->belongsTo(Pu::class);
    }

    public function extension(): string
    {
        return strtolower(pathinfo($this->nombre, PATHINFO_EXTENSION));
    }

    public function esImagen(): bool
    {
        return in_array($this->extension(), self::IMAGENES, true);
    }
}
