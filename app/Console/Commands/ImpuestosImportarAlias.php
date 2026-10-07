<?php

namespace App\Console\Commands;

use App\Models\ImpuestoDocumento;
use App\Support\ImpuestosPdfs;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Asigna los PDF «sin cliente» según el Excel que rellena Alex (7-oct-2026): para cada texto detectado, la entidad indicada (se recuerda como alias);
 * las filas con OBS «no es impuesto» pasan a documento «otro» (no marcan nada) y, si no tienen cliente, se descartan.
 *   python3 Contabilidad/Impuestos/herramientas/exportar_alias_pdf.py -o alias.json && php artisan impuestos:importar-alias alias.json [--aplicar]
 */
class ImpuestosImportarAlias extends Command
{
    protected $signature = 'impuestos:importar-alias {fichero} {--aplicar}';

    protected $description = 'Asigna a entidades los PDF de impuestos sin cliente a partir del Excel rellenado';

    public function handle(): int
    {
        $d = json_decode((string) @file_get_contents($this->argument('fichero')), true);
        if (! isset($d['filas'])) {
            $this->error('JSON no válido.');

            return 1;
        }
        $aplicar = (bool) $this->option('aplicar');
        $idx = ImpuestosPdfs::indiceNombres();
        $porNombre = DB::table('entidades')->get(['id', 'entidad'])->mapWithKeys(fn ($e) => [ImpuestosPdfs::normalizar($e->entidad) => $e->id]);
        $alias = $otros = $descartados = 0;
        $sinResolver = [];
        $vistos = [];
        foreach ($d['filas'] as $f) {
            $noEs = stripos($f['obs'], 'no es impuesto') !== false;
            $doc = ImpuestoDocumento::where('nombre', $f['fichero'])->whereNull('entidad_id')->first();
            if ($f['cliente'] === '') {
                if ($noEs && $doc) {
                    $descartados++;
                    $aplicar && $doc->delete();
                }
                continue;
            }
            $n = ImpuestosPdfs::normalizar($f['cliente']);
            $id = $porNombre[$n] ?? ImpuestosPdfs::entidadPorTexto($f['cliente'], $idx);
            if (! $id) {
                $sinResolver[$f['cliente']] = ($sinResolver[$f['cliente']] ?? 0) + 1;
                continue;
            }
            $a = ImpuestosPdfs::normalizar($f['texto']);
            if ($a !== '' && ! isset($vistos[$a])) {
                $vistos[$a] = true;
                $alias++;
                $aplicar && DB::table('impuesto_alias')->updateOrInsert(['alias' => $a], ['entidad_id' => $id, 'etiqueta' => '', 'created_at' => now(), 'updated_at' => now()]);
            }
            if ($noEs && $doc) {
                $otros++;
                $aplicar && $doc->update(['tipo' => 'otro']);
            }
        }
        $this->info("Alias: {$alias} · pasan a «otro»: {$otros} · descartados: $descartados".($aplicar ? '' : ' (sin aplicar: usa --aplicar)'));
        foreach ($sinResolver as $c => $n) {
            $this->warn("  sin resolver: «{$c}» ({$n})");
        }
        if ($aplicar) {
            $this->info('PDF asignados ahora: '.ImpuestosPdfs::reasociarSinAsignar());
            // los marcados «no es impuesto» no deben haber pasado a verde por el camino: se quedan como «otro»
            $this->info('Sin cliente que quedan: '.ImpuestoDocumento::whereNull('entidad_id')->where('origen', 'onedrive')->count());
        }

        return 0;
    }
}
