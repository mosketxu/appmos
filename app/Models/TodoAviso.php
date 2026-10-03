<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Aviso de la campana del TO-DO. Ver la migración create_todo_avisos. */
class TodoAviso extends Model
{
    protected $table = 'todo_avisos';

    protected $fillable = ['user_id', 'tarea_id', 'origen_id', 'texto', 'leido_at'];

    protected $casts = ['leido_at' => 'datetime'];

    public function tarea(): BelongsTo
    {
        return $this->belongsTo(TodoTarea::class, 'tarea_id');
    }

    public function origen(): BelongsTo
    {
        return $this->belongsTo(User::class, 'origen_id');
    }

    public function scopeSinLeer($q)
    {
        return $q->whereNull('leido_at');
    }

    /** Avisa a $userIds (menos a quien hace la acción) de algo de la tarea. */
    public static function para(TodoTarea $t, array $userIds, string $texto, ?int $actor = null): void
    {
        $actor ??= auth()->id();
        $claude = \App\Support\TodoClaude::usuario()?->id;
        foreach (array_unique(array_map('intval', $userIds)) as $uid) {
            if ($uid !== $actor && $uid !== $claude) {
                static::create(['user_id' => $uid, 'tarea_id' => $t->id, 'origen_id' => $actor, 'texto' => $texto]);
            }
        }
    }
}
