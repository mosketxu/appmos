<?php

namespace App\Console\Commands;

use App\Support\GraphMail;
use Illuminate\Console\Command;

/**
 * Informe mensual de espacio del VPS (11-oct-2026). Lo prepara /usr/local/bin/informe_espacio_appmos.py (como root, que es quien puede
 * medir todo: logs, MySQL...) y lo manda con este comando: `php artisan informe:espacio <ruta.json>` (--simula = lo escribe sin enviarlo).
 * Código del recolector: Contabilidad/TrabajadorWeb/informe_espacio_appmos.py.
 */
class InformeEspacio extends Command
{
    protected $signature = 'informe:espacio {datos : JSON preparado por informe_espacio_appmos.py} {--simula}';

    protected $description = 'Manda a Alex el informe mensual de espacio y crecimiento del VPS';

    public function handle(): int
    {
        $d = json_decode((string) @file_get_contents($this->argument('datos')), true);
        if (! is_array($d)) {
            $this->error('No puedo leer los datos.');

            return 1;
        }
        $mb = fn ($kb) => number_format($kb / 1024, 0, ',', '.').' MB';
        $dif = fn ($kb) => ($kb >= 0 ? '+' : '−').number_format(abs($kb) / 1024, 0, ',', '.').' MB';
        $t = "Informe mensual de espacio del VPS · {$d['mes']}\n\n";
        $t .= "Disco: {$d['usado_gb']} GB usados de {$d['total_gb']} GB ({$d['pct']} %), libres {$d['libre_gb']} GB.\n";
        if (isset($d['pct_anterior'])) {
            $t .= "Mes anterior ({$d['mes_anterior']}): {$d['usado_anterior_gb']} GB ({$d['pct_anterior']} %).\n";
        }
        if (! empty($d['avisos'])) {
            $t .= "\n⚠️ A MIRAR\n".implode("\n", array_map(fn ($a) => '· '.$a, $d['avisos']))."\n";
        } else {
            $t .= "\nNada que hacer este mes.\n";
        }
        $t .= "\nQUÉ OCUPA (mayores primero; entre paréntesis, cambio respecto al mes anterior)\n";
        foreach ($d['items'] as $i) {
            $t .= '· '.str_pad($i['nombre'], 34).' '.str_pad($mb($i['kb']), 10, ' ', STR_PAD_LEFT)
                .(isset($i['antes']) ? '  ('.$dif($i['kb'] - $i['antes']).')' : '  (nuevo)')."\n";
        }
        $t .= "\nLIMPIEZA AUTOMÁTICA (domingos)\n";
        $t .= '· Borrado este mes: '.$mb($d['limpieza_kb'])." en {$d['limpieza_ejecuciones']} ejecuciones.\n";
        $t .= "· Plazos: lo apartado de Facturas OCR (_eliminadas, _vaciadas) 60 días; temporales del Genérico 30 días.\n";
        $t .= "\nDetalle y script: Contabilidad/TrabajadorWeb/DIAGNOSTICO.md (apartado «Limpieza de temporales»).\n";
        if ($this->option('simula')) {
            $this->line($t);

            return 0;
        }
        $para = config('contabilidad.graph.sender');
        GraphMail::enviar($para, [$para], [], "Informe mensual de espacio del VPS · {$d['mes']}", $t);
        $this->info("Enviado a {$para}.");

        return 0;
    }
}
