<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Documento anonimizado (ver la migración confidencialidad_docs y App\Support\Confidencialidad). */
class ConfidencialidadDoc extends Model
{
    protected $table = 'confidencialidad_docs';

    protected $fillable = ['user_id', 'titulo', 'estado', 'error', 'informe', 'archivos', 'tarea_id'];

    protected $casts = ['informe' => 'array', 'archivos' => 'array'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tarea(): BelongsTo
    {
        return $this->belongsTo(TodoTarea::class, 'tarea_id');
    }
}
