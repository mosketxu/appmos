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
    use HasRoles {
        hasPermissionTo as protected traitHasPermissionTo;
    }

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

    /**
     * Accesos denegados a esta persona aunque su rol los dé (tabla permisos_denegados). Admin no se ve afectado (Gate::before).
     * Spatie llama a hasPermissionTo desde Gate/can(): aquí se corta.
     */
    public function hasPermissionTo($permission, $guardName = null): bool
    {
        $nombre = is_string($permission) ? $permission : ($permission->name ?? null);
        if ($nombre !== null && in_array($nombre, \App\Support\Accesos::denegados($this->id), true)) {
            return false;
        }

        return $this->traitHasPermissionTo($permission, $guardName);
    }

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

    /** El correo de «¿Olvidaste tu contraseña?» sale por Graph (ver ResetPasswordGraph). */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new \App\Notifications\ResetPasswordGraph($token));
    }
}
