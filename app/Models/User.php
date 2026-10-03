<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;
    use HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'activo',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'activo' => 'boolean',
    ];

    /** Responsable Suma (combo de Entidades) que es este usuario, si lo es. */
    public function suma()
    {
        return $this->hasOne(Suma::class);
    }

    /** Entidades asignadas a mano en el panel de control (además de las que lleva como Responsable Suma). */
    public function entidadesAsignadas()
    {
        return $this->belongsToMany(Entidad::class, 'entidad_user')->withTimestamps();
    }

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'profile_photo_url',
    ];

    /**
     * El correo de «¿Olvidaste tu contraseña?» sale por Microsoft Graph (como el resto de correos de Appmos),
     * desde el buzón GRAPH_SENDER. Si Graph falla se anota en el log y el usuario ve el aviso de siempre
     * (no se revela si el correo existe).
     */
    public function sendPasswordResetNotification($token): void
    {
        $enlace = url(route('password.reset', ['token' => $token, 'email' => $this->email], false));
        $minutos = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');
        $texto = "Hola {$this->name},\n\n"
            ."Has pedido restablecer tu contraseña de Appmos. Pulsa aquí para elegir una nueva:\n{$enlace}\n\n"
            ."El enlace caduca en {$minutos} minutos. Si no lo has pedido tú, ignora este correo.\n\n"
            ."Suma Apoyo Empresarial SL";
        try {
            \App\Support\GraphMail::enviar(config('contabilidad.graph.sender'), [$this->email], [], 'Restablecer tu contraseña de Appmos', $texto);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
