<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Correo de Proc.Mensuales (pendiente con enviar_ahora, o ya enviado). Ver la migración. */
class MailEnviado extends Model
{
    protected $table = 'mails_enviados';

    protected $fillable = ['proceso', 'entidad_id', 'periodo', 'user_id', 'enviar_ahora', 'idioma',
        'destinatarios', 'cc', 'asunto', 'texto', 'enviado_at', 'error'];

    protected $casts = [
        'enviar_ahora' => 'boolean',
        'enviado_at' => 'datetime',
    ];

    public function entidad()
    {
        return $this->belongsTo(Entidad::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
