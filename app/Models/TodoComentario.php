<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TodoComentario extends Model
{
    protected $table = 'todo_comentarios';

    protected $fillable = ['tarea_id', 'user_id', 'fecha', 'texto'];

    protected $casts = ['fecha' => 'date'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
