<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** PDF de un impuesto (presentado, borrador u otro justificante). El fichero está en storage/app/<almacen>. */
class ImpuestoDocumento extends Model
{
    protected $table = 'impuesto_documentos';
    protected $fillable = ['entidad_id', 'modelo', 'etiqueta', 'ejercicio', 'periodo', 'tipo', 'nombre', 'cliente_texto', 'ruta_origen', 'almacen', 'tam', 'mtime', 'sha256', 'origen', 'user_id', 'quitado_at', 'quitado_por'];
}
