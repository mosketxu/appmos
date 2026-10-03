<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Tarea de la pestaña TO-DO. Ver la migración create_todo_tables. */
class TodoTarea extends Model
{
    protected $table = 'todo_tareas';

    protected $fillable = ['titulo', 'descripcion', 'creador_id', 'estado', 'prioridad', 'fecha_limite', 'cerrada_at', 'claude_autorizada_at', 'claude_autorizada_por', 'claude_pausada', 'claude_permisos', 'prioridad_pedida_at', 'prioridad_pedida_por'];

    protected $casts = [
        'fecha_limite' => 'date',
        'cerrada_at' => 'datetime',
        'claude_autorizada_at' => 'datetime',
        'claude_pausada' => 'boolean',
        'claude_permisos' => 'array',
        'prioridad_pedida_at' => 'datetime',
    ];

    public const ESTADOS = [
        'pendiente' => 'Pendiente',
        'en_curso' => 'En curso',
        'bloqueada' => 'Bloqueada',
        'hecha' => 'Hecha',
        'cancelada' => 'Cancelada',
    ];

    /**
     * Permisos que solo Alex concede a Claude para una tarea. Sin ninguno: lee, edita ficheros del proyecto, hace tests y commits locales.
     * Los técnicos se imponen en el PC (claude_todo.py: lista de herramientas); «correo» y «borrar» además van en las instrucciones.
     */
    public const PERMISOS_CLAUDE = [
        'scripts' => ['Ejecutar scripts (python / node / tinker)', 'Hace falta para casi cualquier proceso de Contabilidad. Ojo: con scripts puede hacer casi de todo.'],
        'correo' => ['Enviar correos', 'Por Graph, desde un script. Implica scripts.'],
        'desplegar' => ['Desplegar / hacer push', 'git push, subir al VPS o a producción.'],
        'ssh' => ['Usar ssh / scp (VPS)', 'Conectarse a servidores.'],
        'borrar' => ['Borrar o sobrescribir ficheros', 'rm, mv, cp sobre datos o ficheros de clientes.'],
    ];

    public const PRIORIDADES = ['baja' => 'Baja', 'normal' => 'Normal', 'alta' => 'Alta'];

    /** Estados que cuentan como cerrada. */
    public const CERRADOS = ['hecha', 'cancelada'];

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creador_id');
    }

    /** Personas a las que está asignada; el pivot guarda el orden de prioridad de cada una (1 = lo primero). */
    public function asignados(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'todo_tarea_user', 'tarea_id', 'user_id')->withPivot('orden')->orderBy('users.name');
    }

    public function prioridadPedidaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prioridad_pedida_por');
    }

    public function comentarios(): HasMany
    {
        return $this->hasMany(TodoComentario::class, 'tarea_id')->orderBy('fecha')->orderBy('id');
    }

    /** Orden que le toca a una tarea nueva (o que se le asigna) a $userId: la última de su lista. */
    public static function siguienteOrden(int $userId): int
    {
        return 1 + (int) \DB::table('todo_tarea_user')->where('user_id', $userId)->max('orden');
    }

    public function abierta(): bool
    {
        return ! in_array($this->estado, self::CERRADOS, true);
    }

    /** Tareas de un usuario: las que ha creado o le han asignado. */
    public function scopeDe($query, int $userId)
    {
        return $query->where(fn ($q) => $q->where('creador_id', $userId)->orWhereHas('asignados', fn ($a) => $a->where('users.id', $userId)));
    }

    public function estaAsignadaA(int $userId): bool
    {
        return $this->asignados->contains('id', $userId);
    }
}
