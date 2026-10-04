<?php

namespace App\Support;

use App\Models\User;
use App\Support\ColaTareas;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

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
        return static::$cache[$user->id] = static::entidadesPropias($user);
    }

    /**
     * Entidades que son del usuario según el panel de control (las que lleva como
     * Responsable Suma + las asignadas a mano), tenga o no entidades.todas.
     */
    public static function entidadesPropias(User $user): array
    {
        // Sin pasar por el modelo Entidad (su scope global llamaría aquí otra vez)
        $porResponsable = DB::table('entidades')
            ->join('sumas', 'sumas.id', '=', 'entidades.suma_id')
            ->where('sumas.user_id', $user->id)
            ->pluck('entidades.id');
        $asignadas = DB::table('entidad_user')->where('user_id', $user->id)->pluck('entidad_id');

        return $porResponsable->merge($asignadas)->unique()->map(fn ($id) => (int) $id)->values()->all();
    }

    /** Procesos de una pestaña: [clave de permiso => texto]. Vacío si la pestaña no tiene procesos propios. */
    public static function procesosDe(string $permisoPestana): array
    {
        $c = config('accesos.procesos', [])[$permisoPestana] ?? null;
        if (! $c) {
            return [];
        }
        $lista = $c['lista'] ?? [];
        if (($c['fuente'] ?? '') === 'fiq') {
            $lista = [];
            try {
                $d = \Illuminate\Support\Facades\Schema::hasTable('estado_procesos') ? ColaTareas::estado('fiq.checklist_def') : null;
            } catch (\Throwable $e) {
                $d = null;
            }
            if (! is_array($d)) {   // en un PC: el fichero de checklist
                $d = null;
                foreach (['e', 'f', 'd'] as $u) {
                    if (is_file($f = "/mnt/{$u}/Claude/Contabilidad/monthlyFIQ/checklist.json")) {
                        $d = json_decode((string) file_get_contents($f), true);
                        break;
                    }
                }
            }
            foreach ($d['procesos'] ?? [] as $p) {
                $lista[$p['id']] = $p['nombre'] ?? $p['id'];
            }
        }
        $out = [];
        foreach ($lista as $id => $texto) {
            $out[$c['prefijo'].$id] = $texto;
        }
        return $out;
    }

    /**
     * Todo lo que se puede conceder, ordenado por bloques de la aplicación:
     * [bloque => [['clave' => permiso, 'texto' => ..., 'hijos' => [permiso de proceso => texto]], ...]].
     * Los hijos son los procesos de esa pestaña (vacío si no tiene procesos propios).
     */
    public static function arbol(): array
    {
        $out = [];
        foreach (config('accesos.permisos') as $bloque => $permisos) {
            foreach ($permisos as $clave => $texto) {
                $out[$bloque][] = ['clave' => $clave, 'texto' => $texto, 'hijos' => static::procesosDe($clave)];
            }
        }
        return $out;
    }

    /** Permiso de la pestaña a la que pertenece un permiso de proceso (o null si no lo es). */
    public static function padreDeProceso(string $permiso): ?string
    {
        foreach (config('accesos.procesos', []) as $pestana => $c) {
            if (str_starts_with($permiso, $c['prefijo'])) {
                return $pestana;
            }
        }
        return null;
    }

    public static function existe(string $permiso): bool
    {
        return app(PermissionRegistrar::class)->getPermissions(['name' => $permiso, 'guard_name' => 'web'])->isNotEmpty();
    }

    /**
     * Crea el permiso de un proceso si no existe y se lo da a quien ya tenía la pestaña (roles y usuarios con permiso directo),
     * para que nadie pierda lo que ya podía hacer.
     */
    public static function asegurarProceso(string $permiso): void
    {
        $padre = static::padreDeProceso($permiso);
        if (! $padre || static::existe($permiso)) {
            return;
        }
        $nuevo = Permission::findOrCreate($permiso, 'web');
        foreach (\Spatie\Permission\Models\Role::all() as $rol) {
            if ($rol->hasPermissionTo($padre)) {
                $rol->givePermissionTo($nuevo);
            }
        }
        $idPadre = Permission::where('name', $padre)->where('guard_name', 'web')->value('id');
        $ids = DB::table('model_has_permissions')->where('permission_id', $idPadre)->where('model_type', User::class)->pluck('model_id');
        foreach (User::whereIn('id', $ids)->get() as $u) {
            if (! $u->hasPermissionTo($nuevo)) {
                $u->givePermissionTo($nuevo);
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public static function olvidar(): void
    {
        static::$cache = [];
    }
}
