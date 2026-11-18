<?php

namespace App\Console\Commands;

use App\Support\FiqIc;
use App\Support\FiqIcValidacion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Lo llama el trabajador (FacturasOcr/omc_excel.py) cuando llegan por correo las facturas de Plein de OMC/Breuninger: deja las compras
 * como PENDIENTES en el panel «Facturación» de Procesos FIQ (Alex solo revisa y emite). Idempotente: una factura de Plein
 * (proveedor + nº + signo) que ya está apuntada, venga de donde venga, no se vuelve a apuntar. Entrada: JSON por stdin.
 */
class FiqIcAnadir extends Command
{
    protected $signature = 'fiq:ic-anadir {empresa : Breuninger|OMC} {periodo : AAAA-MM del mes en que se emite la venta} {--dry : solo cuenta, no escribe}';
    protected $description = 'Apunta como pendientes las compras de Plein recibidas (JSON por stdin: cuenta, numero, fecha, canal, importe)';

    public function handle(): int
    {
        $emp = (string) $this->argument('empresa');
        $per = (string) $this->argument('periodo');
        $lineas = json_decode((string) stream_get_contents(STDIN), true);
        if (! in_array($emp, FiqIc::empresas(), true) || ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $per) || ! is_array($lineas)) {
            $this->line(json_encode(['error' => 'parámetros no válidos']));

            return 1;
        }
        $cuentas = FiqIc::cuentasDe($emp);
        $ins = $ya = 0;
        $avisos = [];
        foreach ($lineas as $l) {
            $cuenta = (int) ($l['cuenta'] ?? 0);
            $numero = trim((string) ($l['numero'] ?? ''));
            $importe = round((float) ($l['importe'] ?? 0), 2);
            $canal = $l['canal'] ?? null;
            if (! isset($cuentas[$cuenta]) || $numero === '' || ! array_key_exists((int) $canal, FiqIc::TIENDAS) || $canal === null) {
                $avisos[] = "línea no válida: {$numero} (cuenta {$cuenta}, canal ".json_encode($canal).')';
                continue;
            }
            $dig = FiqIcValidacion::digitos($numero);
            $existe = DB::table('fiq_ic_compras')->where('cuenta', $cuenta)->get()->contains(
                fn ($c) => FiqIcValidacion::digitos($c->numero) === $dig && ($c->importe < 0) === ($importe < 0) && abs(abs((float) $c->importe) - abs($importe)) < 0.005
            );
            if ($existe) {
                $ya++;
                continue;
            }
            if (! $this->option('dry')) {
                DB::table('fiq_ic_compras')->insert([
                    'empresa' => $emp, 'periodo' => $per, 'cuenta' => $cuenta, 'numero' => $numero, 'fecha' => $l['fecha'] ?? null, 'canal' => (int) $canal,
                    'importe' => $importe, 'origen' => 'claude', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $ins++;
        }
        $this->line(json_encode(['insertadas' => $ins, 'ya_estaban' => $ya, 'avisos' => $avisos, 'dry' => (bool) $this->option('dry')]));

        return 0;
    }
}
