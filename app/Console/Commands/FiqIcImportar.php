<?php

namespace App\Console\Commands;

use App\Support\FiqIc;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Importa el histórico del Excel de facturación Breuninger/OMC (JSON sacado con Contabilidad/monthlyFIQ/FacturacionIC/extraer_excel.py). Repetible: borra antes lo importado. */
class FiqIcImportar extends Command
{
    protected $signature = 'fiq:ic-importar {json : fichero JSON de bloques} {--anio=2026}';
    protected $description = 'Importa el histórico del Excel de facturación Breuninger/OMC a las tablas fiq_ic_*';

    public function handle(): int
    {
        $bloques = json_decode((string) file_get_contents($this->argument('json')), true);
        if (! is_array($bloques)) {
            $this->error('JSON no válido');
            return 1;
        }
        $anio = (int) $this->option('anio');
        DB::transaction(function () use ($bloques, $anio) {
            DB::table('fiq_ic_compras')->where('origen', 'excel')->delete();
            DB::table('fiq_ic_emisiones')->where('origen', 'excel')->delete();
            foreach ($bloques as $b) {
                $ref = (string) ($b['ref'] ?? '');
                if (! preg_match('/^(R )?(BR|OMC)(\d\d)_(\d+)$/', $ref, $m) || empty($b['lineas'])) {
                    $this->warn("Salto bloque «{$ref}»");
                    continue;
                }
                $empresa = $m[2] === 'OMC' ? 'OMC' : 'Breuninger';
                $abono = $m[1] !== '';
                $fe = $b['lineas'][0]['fecha_emision'] ?? null;
                $maxFecha = collect($b['lineas'])->pluck('fecha')->filter()->max();
                $fecha = $fe ?: $maxFecha ?: "{$anio}-12-31";
                $calc = FiqIc::resumen($b['lineas'])['total'];
                $totalExcel = is_numeric($b['total_excel'] ?? null) ? round((float) $b['total_excel'], 2) : $calc;
                $nota = $b['nota'] ?? '';
                if (abs($totalExcel - $calc) > 1) { // fallo del Excel (p. ej. BR26_10: celda tecleada a mano); manda la suma de las líneas
                    $nota = trim($nota." El Excel decía {$totalExcel}; se usa la suma de las líneas.");
                    $totalExcel = $calc;
                }
                $id = DB::table('fiq_ic_emisiones')->insertGetId([
                    'empresa' => $empresa, 'periodo' => substr($fecha, 0, 7), 'serie' => $m[2].$m[3],
                    'numero' => $abono ? null : (int) $m[4], 'tipo' => $abono ? 'abono' : 'factura',
                    'fecha' => $fecha, 'total' => $totalExcel, 'total_calculado' => $calc,
                    'nota' => trim(($abono ? "Abono de {$m[2]}{$m[3]}_{$m[4]}. " : '').$nota) ?: null,
                    'origen' => 'excel', 'created_at' => now(), 'updated_at' => now(),
                ]);
                foreach ($b['lineas'] as $l) {
                    DB::table('fiq_ic_compras')->insert([
                        'empresa' => $empresa, 'periodo' => substr($fecha, 0, 7), 'cuenta' => $l['cuenta'], 'numero' => $l['numero'],
                        'fecha' => $l['fecha'], 'canal' => (int) $l['canal'], 'importe' => $l['importe'], 'emision_id' => $id,
                        'origen' => 'excel', 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
                $dif = round($totalExcel - $calc, 2);
                $this->line(sprintf('%s %s: %d líneas, total %.2f%s', $empresa, $ref, count($b['lineas']), $totalExcel, abs($dif) > 0.001 ? " (calculado {$calc}: dif {$dif})" : ''));
            }
        });

        return 0;
    }
}
