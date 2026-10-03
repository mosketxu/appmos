<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Tarea de la pestaña TO-DO. Ver la migración create_todo_tables. */
class TodoTarea extends Model
{
    protected $table = 'todo_tareas';

    protected $fillable = ['titulo', 'descripcion', 'creador_id', 'asignado_id', 'estado', 'prioridad', 'fecha_limite', 'cerrada_at'];

    protected $casts = [
        'fecha_limite' => 'date',
        'cerrada_at' => 'datetime',
    ];

    public const ESTADOS = [
        'pendiente' => 'Pendiente',
        'en_curso' => 'En curso',
        'bloqueada' => 'Bloqueada',
        'hecha' => 'Hecha',
        'cancelada' => 'Cancelada',
    ];

    public const PRIORIDADES = ['baja' => 'Baja', 'normal' => 'Normal', 'alta' => 'Alta'];

    /** Estados que cuentan como cerrada. */
    public const CERRADOS = ['hecha', 'cancelada'];

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creador_id');
    }

    public function asignado(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignado_id');
    }

    public function comentarios(): HasMany
    {
        return $this->hasMany(TodoComentario::class, 'tarea_id')->orderBy('fecha')->orderBy('id');
    }

    public function abierta(): bool
    {
        return ! in_array($this->estado, self::CERRADOS, true);
    }

    /** Tareas de un usuario: las que ha creado o le han asignado. */
    public function scopeDe($query, int $userId)
    {
        return $query->where(fn ($q) => $q->where('creador_id', $userId)->orWhere('asignado_id', $userId));
    }
}
