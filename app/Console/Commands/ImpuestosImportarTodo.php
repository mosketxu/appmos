<?php

namespace App\Console\Commands;

use App\Support\Impuestos;
use App\Support\ImpuestosPdfs;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Carga en Impuestos lo que dice el «ToDO Alex.xlsx» (7-oct-2026): qué impuestos presenta cada cliente, con qué periodicidad y en qué
 * estado está cada periodo del ejercicio. Primero se pasa el Excel a JSON con
 *   python3 Contabilidad/Impuestos/herramientas/exportar_todo_impuestos.py -o todo.json
 * y luego `php artisan impuestos:importar-todo todo.json` (solo informa) o con `--aplicar`.
 * Es idempotente: no pisa una obligación ni una casilla que ya existan (lo que se haya cambiado en la web se respeta).
 * Los periodos del ejercicio que el Excel no menciona se guardan como «no» (vacío).
 */
class ImpuestosImportarTodo extends Command
{
    protected $signature = 'impuestos:importar-todo {fichero} {--ejercicio=2026} {--aplicar}';

    protected $description = 'Carga en Impuestos las obligaciones y estados del ToDO Alex (JSON del exportador)';

    public function handle(): int
    {
        $d = json_decode((string) @file_get_contents($this->argument('fichero')), true);
        if (! is_array($d) || ! isset($d['clientes'])) {
            $this->error('JSON no válido.');

            return 1;
        }
        $ejercicio = (int) $this->option('ejercicio');
        $aplicar = (bool) $this->option('aplicar');
        $porCodigo = DB::table('entidades')->whereNotNull('codigo_cliente')->pluck('id', 'codigo_cliente')->all();
        $idx = ImpuestosPdfs::indiceNombres();
        $modelos = DB::table('impuesto_modelos')->pluck('id', 'codigo')->all();
        $ahora = now();
        $porCod = $porNombre = $sin = $obligaciones = $filas = $conAlias = 0;
        $sinLista = [];

        foreach ($d['clientes'] as $c) {
            $id = $c['cod_cli'] && isset($porCodigo[$c['cod_cli']]) ? (int) $porCodigo[$c['cod_cli']] : null;
            if ($id) {
                $porCod++;
            } else {
                $id = ImpuestosPdfs::entidadPorTexto((string) $c['cliente'], $idx) ?: ($c['archivo'] ? ImpuestosPdfs::entidadPorTexto((string) $c['archivo'], $idx) : null);
                $id ? $porNombre++ : $sin++;
            }
            if (! $id) {
                if ($c['obligaciones']) {
                    $sinLista[] = sprintf('%s (%s) · %d impuestos', $c['cliente'], $c['cod_cli'] ?? 's/código', count($c['obligaciones']));
                }
                continue;
            }
            if ($aplicar) {   // el nombre con el que sale en los ficheros de OneDrive (columna «Cliente» del final del Excel)
                foreach (array_filter([$c['archivo'], $c['cliente']]) as $nom) {
                    $a = ImpuestosPdfs::normalizar($nom);
                    if (strlen($a) >= 3 && ! isset($idx[$a][$id]) && ! DB::table('impuesto_alias')->where('alias', $a)->exists()) {
                        DB::table('impuesto_alias')->insert(['alias' => $a, 'entidad_id' => $id, 'created_at' => $ahora, 'updated_at' => $ahora]);
                        $conAlias++;
                    }
                }
            }
            foreach ($c['obligaciones'] as $o) {
                $modeloId = $modelos[$o['modelo']] ?? null;
                if (! $modeloId) {
                    continue;
                }
                $obligaciones++;
                if (! $aplicar) {
                    continue;
                }
                $ob = DB::table('entidad_impuestos')->where('entidad_id', $id)->where('modelo_id', $modeloId)->first();
                $obId = $ob->id ?? DB::table('entidad_impuestos')->insertGetId(['entidad_id' => $id, 'modelo_id' => $modeloId, 'periodicidad' => $o['periodicidad'],
                    'created_at' => $ahora, 'updated_at' => $ahora]);
                if ($ob && $ob->periodicidad !== $o['periodicidad']) {
                    $this->warn("{$c['cliente']} M{$o['modelo']}: ya estaba como {$ob->periodicidad}, el Excel dice {$o['periodicidad']} (no se cambia)");
                    continue;
                }
                $nuevas = [];
                foreach (Impuestos::periodos($o['periodicidad']) as $p) {
                    $nuevas[] = ['entidad_impuesto_id' => $obId, 'ejercicio' => $ejercicio, 'periodo' => $p, 'estado' => $o['estados'][$p] ?? 'no', 'created_at' => $ahora, 'updated_at' => $ahora];
                }
                $filas += DB::table('impuesto_estados')->insertOrIgnore($nuevas);
            }
        }
        $this->info(sprintf('Clientes: %d por código, %d por nombre, %d sin casar · obligaciones: %d%s', $porCod, $porNombre, $sin, $obligaciones,
            $aplicar ? " · casillas nuevas: $filas · alias nuevos: $conAlias" : ' (sin aplicar: usa --aplicar)'));
        foreach ($sinLista as $s) {
            $this->line('  sin casar: '.$s);
        }

        return 0;
    }
}
