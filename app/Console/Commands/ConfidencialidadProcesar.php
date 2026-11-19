<?php

namespace App\Console\Commands;

use App\Models\ConfidencialidadDoc;
use App\Support\Confidencialidad;
use Illuminate\Console\Command;

/** Anonimiza en segundo plano los originales de un documento de Confidencialidad (lo lanza Confidencialidad::lanzar). De uno en uno: el VPS tiene 1 CPU. */
class ConfidencialidadProcesar extends Command
{
    protected $signature = 'confidencialidad:procesar {id}';

    protected $description = 'Anonimiza los originales de un documento de Confidencialidad (OCR por página) y deja su estado en borrador o error';

    public function handle(): int
    {
        $d = ConfidencialidadDoc::find((int) $this->argument('id'));
        if (! $d) {
            return self::FAILURE;
        }
        $cerrojo = fopen(storage_path('app/confidencialidad/.cola.lock'), 'c');
        flock($cerrojo, LOCK_EX);                  // si hay otro procesando, espera su turno
        try {
            if (! ConfidencialidadDoc::find($d->id)) {   // borrado mientras esperaba
                return self::SUCCESS;
            }
            $hecho = Confidencialidad::procesar($d->fresh());
            Confidencialidad::alTerminar($hecho->fresh() ?? $hecho);
        } catch (\Throwable $e) {
            $d->fresh()?->update(['estado' => 'error', 'error' => 'Fallo al anonimizar: '.$e->getMessage()]);
            report($e);

            return self::FAILURE;
        } finally {
            flock($cerrojo, LOCK_UN);
            fclose($cerrojo);
        }

        return self::SUCCESS;
    }
}
