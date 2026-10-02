<?php

namespace App\Console\Commands;

use App\Support\ColaTareas;
use Illuminate\Console\Command;

class TrabajadorCrear extends Command
{
    protected $signature = 'trabajador:crear {nombre : AlexMiniPC, PortalExomen...}';
    protected $description = 'Da de alta (o renueva el token de) un PC trabajador y muestra el token una sola vez';

    public function handle(): int
    {
        $token = ColaTareas::crearTrabajador($this->argument('nombre'));
        $this->info('Token de '.$this->argument('nombre').' (guárdalo en ~/.appmos_trabajador.json de ese PC; no se vuelve a mostrar):');
        $this->line($token);

        return self::SUCCESS;
    }
}
