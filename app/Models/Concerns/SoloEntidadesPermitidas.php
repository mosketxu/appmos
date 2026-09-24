<?php

namespace App\Models\Concerns;

use App\Support\Accesos;
use Illuminate\Database\Eloquent\Builder;

/**
 * Scope global: un usuario sin "entidades.todas" solo ve las filas de sus
 * entidades. En Entidad la columna es id; en el resto, entidad_id.
 */
trait SoloEntidadesPermitidas
{
    public static function bootSoloEntidadesPermitidas(): void
    {
        static::addGlobalScope('entidadesPermitidas', function (Builder $q) {
            $ids = Accesos::entidadesPermitidas();
            if ($ids !== null) {
                $modelo = $q->getModel();
                $columna = $modelo->getTable().'.'.($modelo instanceof \App\Models\Entidad ? 'id' : 'entidad_id');
                $q->whereIn($columna, $ids ?: [0]);
            }
        });
    }
}
