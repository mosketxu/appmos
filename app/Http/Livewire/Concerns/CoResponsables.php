<?php

namespace App\Http\Livewire\Concerns;

use App\Models\Entidad;
use App\Models\Suma;
use App\Support\Accesos;
use Illuminate\Support\Facades\DB;

/**
 * Co-responsables («Otro Resp.») de una entidad, además del Responsable Suma (suma_id).
 * Se guardan en entidad_user (las mismas «asignadas a mano» del panel de control), por
 * eso cada co-responsable es el usuario de un responsable de la tabla sumas.
 * La vista pinta las etiquetas y el modal con livewire.ents._coresp.
 */
trait CoResponsables
{
    /** Entidad cuyo modal de co-responsables está abierto. */
    public ?int $coRespDe = null;

    /** [entidad_id => [['suma_id' => , 'nombre' => ], ...]] de las entidades dadas. */
    public static function coResponsablesDe(array $entidadIds): array
    {
        $out = [];
        $filas = DB::table('entidad_user')->join('sumas', 'sumas.user_id', '=', 'entidad_user.user_id')
            ->whereIn('entidad_user.entidad_id', $entidadIds ?: [0])
            ->orderBy('sumas.nombre')->get(['entidad_user.entidad_id', 'sumas.id as suma_id', 'sumas.nombre']);
        foreach ($filas as $f) {
            $out[$f->entidad_id][] = ['suma_id' => $f->suma_id, 'nombre' => $f->nombre];
        }
        return $out;
    }

    public function abrirCoResp(int $entidadId): void
    {
        if (auth()->user()->can('entidades.editar') && Entidad::find($entidadId)) {
            $this->coRespDe = $entidadId;
        }
    }

    public function cerrarCoResp(): void
    {
        $this->coRespDe = null;
    }

    /** Pone o quita a un responsable como co-responsable de la entidad (se guarda al momento). */
    public function alternarCoResp(int $entidadId, int $sumaId, ?bool $poner = null): void
    {
        if (! auth()->user()->can('entidades.editar') || ! Entidad::find($entidadId)) {
            return;
        }
        $userId = Suma::whereKey($sumaId)->value('user_id');
        if (! $userId) {
            return;
        }
        $q = DB::table('entidad_user')->where('entidad_id', $entidadId)->where('user_id', $userId);
        $poner ??= ! $q->exists();
        if ($poner && ! $q->exists()) {
            DB::table('entidad_user')->insert(['entidad_id' => $entidadId, 'user_id' => $userId, 'created_at' => now(), 'updated_at' => now()]);
        } elseif (! $poner) {
            $q->delete();
        }
        Accesos::olvidar();
    }
}
