<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EntidadGrupo extends Model
{
    protected $table = 'entidad_grupos';
    protected $fillable = ['nombre'];

    /** Crea el grupo (o devuelve el que ya exista con ese nombre, sin distinguir mayúsculas) para no duplicar por un despiste al escribir. */
    public static function buscarOCrear(string $nombre): ?self
    {
        $nombre = trim(preg_replace('/\s+/', ' ', $nombre));
        if ($nombre === '') {
            return null;
        }

        return self::whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])->first() ?? self::create(['nombre' => $nombre]);
    }
}
