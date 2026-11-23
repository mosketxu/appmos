<?php

namespace App\Console\Commands;

use App\Support\FicherosBase;
use Illuminate\Console\Command;

/** Borra los ficheros base centrales antiguos (históricos) conservando el último de cada empresa y tipo. Semanal en el VPS (cron). */
class FicherosBaseDepurar extends Command
{
    protected $signature = 'ficheros-base:depurar {--dias=30 : antigüedad mínima para borrar} {--simular : solo listar}';

    protected $description = 'Borra los históricos de ficheros base con más de N días (siempre queda el último de cada tipo)';

    public function handle(): int
    {
        $b = FicherosBase::depurar((int) $this->option('dias'), (bool) $this->option('simular'));
        foreach ($b as $r) {
            $this->line(($this->option('simular') ? 'borraría ' : 'borrado ').$r);
        }
        $this->info(count($b).' históricos '.($this->option('simular') ? 'a borrar' : 'borrados').'.');

        return 0;
    }
}
