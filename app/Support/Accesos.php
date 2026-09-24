<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Qué entidades puede ver el usuario conectado. Sin usuario (consola, colas)
 * o con permiso entidades.todas no hay límite (null).
 */
class Accesos
{
    /** @var array<int, array<int>|null> caché por petición */
    protected static array $cache = [];

    /** IDs de entidades permitidas, o null si puede ver todas. */
    public static function entidadesPermitidas(?User $user = null): ?array
    {
        $user ??= auth()->user();
        if (! $user) {
            return null;
        }
        if (array_key_exists($user->id, static::$cache)) {
            return static::$cache[$user->id];
        }
        if ($user->can('entidades.todas')) {
            return static::$cache[$user->id] = null;
        }
        // Sin pasar por el modelo Entidad (su scope global llamaría aquí otra vez)
        $porResponsable = DB::table('entidades')
            ->join('sumas', 'sumas.id', '=', 'entidades.suma_id')
            ->where('sumas.user_id', $user->id)
            ->pluck('entidades.id');
        $asignadas = DB::table('entidad_user')->where('user_id', $user->id)->pluck('entidad_id');

        return static::$cache[$user->id] = $porResponsable->merge($asignadas)->unique()->map(fn ($id) => (int) $id)->values()->all();
    }

    public static function olvidar(): void
    {
        static::$cache = [];
    }
}
