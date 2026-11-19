<?php

namespace App\Console\Commands;

use App\Models\Entidad;
use App\Support\FicherosBase;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Rellena código y cuenta contable de CLIENTE y de PROVEEDOR de las entidades a partir de los ficheros base de SAGE de la empresa
 * (por defecto la 158, Suma: listado de clientes, listado de proveedores y plan de cuentas) cruzando por NIF. 19-nov-2026, orden de Alex.
 * Solo rellena lo VACÍO; lo que ya tiene otro valor se lista como diferencia y no se toca. Sin --aplicar solo informa.
 *   php artisan entidades:rellenar-codigos            (informe)
 *   php artisan entidades:rellenar-codigos --aplicar
 */
class EntidadesRellenarCodigos extends Command
{
    protected $signature = 'entidades:rellenar-codigos {--empresa=158} {--aplicar}';

    protected $description = 'Rellena cód./cuenta contable de cliente y proveedor desde los ficheros base de SAGE';

    protected function nif(?string $v): string
    {
        $v = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $v));

        return $v;
    }

    /** Las dos formas de un NIF (con y sin el prefijo de país de 2 letras) para cruzar «ES123…» con «123…». */
    protected function claves(?string $v): array
    {
        $n = $this->nif($v);
        if ($n === '') {
            return [];
        }
        $k = [$n];
        if (preg_match('/^[A-Z]{2}[A-Z0-9]{7,}$/', $n) && ! preg_match('/^[A-Z]\d{7}[A-Z0-9]$/', $n)) {
            $k[] = substr($n, 2);
        }

        return $k;
    }

    protected function hoja(string $ruta): array
    {
        $r = IOFactory::createReaderForFile($ruta);
        $r->setReadDataOnly(true);

        return $r->load($ruta)->getActiveSheet()->toArray(null, false, false, false);
    }

    public function handle(): int
    {
        $emp = (int) $this->option('empresa');
        $cli = $pro = $plan = [];   // clave NIF => ['cod'=>, 'cta'=>, 'nombre'=>]
        $f = FicherosBase::ultimo($emp, 'clientes');
        if ($f) {
            foreach ($this->hoja($f['ruta']) as $i => $fila) {
                $cod = trim((string) ($fila[0] ?? '')); $cta = trim((string) ($fila[10] ?? ''));
                if (! preg_match('/^\d{4,}$/', $cta)) {
                    continue;   // cabecera y filas de descripción
                }
                foreach ($this->claves($fila[4] ?? '') as $k) {
                    $cli[$k][] = ['cod' => preg_match('/^\d+$/', $cod) ? $cod : $cta, 'cta' => $cta, 'nombre' => (string) ($fila[1] ?? '')];
                }
            }
        }
        $this->line('Clientes de SAGE: '.count($cli).' claves ('.($f['nombre'] ?? 'sin fichero').')');
        $f = FicherosBase::ultimo($emp, 'proveedores');
        if ($f) {
            $filas = $this->hoja($f['ruta']);
            $h = array_flip(array_map('strval', $filas[0] ?? []));
            foreach (array_slice($filas, 1) as $fila) {
                $cod = trim((string) ($fila[$h['CodigoProveedor'] ?? 2] ?? '')); $cta = trim((string) ($fila[$h['CodigoContable'] ?? 22] ?? ''));
                foreach (array_merge($this->claves($fila[$h['CifDni'] ?? 4] ?? ''), $this->claves($fila[$h['CifEuropeo'] ?? 5] ?? '')) as $k) {
                    $pro[$k][] = ['cod' => $cod, 'cta' => $cta ?: $cod, 'nombre' => (string) ($fila[$h['RazonSocial'] ?? 6] ?? '')];
                }
            }
        }
        $this->line('Proveedores de SAGE: '.count($pro).' claves ('.($f['nombre'] ?? 'sin fichero').')');
        $f = FicherosBase::ultimo($emp, 'plan');
        if ($f) {
            foreach ($this->hoja($f['ruta']) as $fila) {
                $cta = trim((string) ($fila[0] ?? ''));
                $tipo = trim((string) ($fila[2] ?? ''));
                if (! preg_match('/^\d{6,}$/', $cta) || ! in_array($tipo, ['Cliente', 'Proveedor'], true)) {
                    continue;
                }
                foreach ($this->claves($fila[4] ?? '') as $k) {
                    $plan[$tipo][$k][] = ['cta' => $cta, 'nombre' => (string) ($fila[1] ?? '')];
                }
            }
        }
        $this->line('Plan de cuentas: '.count($plan['Cliente'] ?? []).' clientes, '.count($plan['Proveedor'] ?? []).' proveedores con CIF');

        $ents = Entidad::withoutGlobalScopes()->whereNull('deleted_at')->where(fn ($q) => $q->where('cliente', 1)->orWhere('proveedor', 1))->orderBy('entidad')->get();
        $nifs = $ents->groupBy(fn ($e) => $this->nif($e->nif));
        $cambios = $difs = $sin = [];
        $n = ['cli' => 0, 'pro' => 0];
        foreach ($ents as $e) {
            $dupl = $this->nif($e->nif) !== '' && $nifs[$this->nif($e->nif)]->count() > 1;
            foreach (['cli' => ['cliente', 'cuentacontable_cliente', 'codigo_cliente', $cli, 'Cliente'], 'pro' => ['proveedor', 'cuentacontable_proveedor', 'codigo_proveedor', $pro, 'Proveedor']] as $t => [$flag, $colCta, $colCod, $fuente, $tipoPlan]) {
                if (! $e->{$flag}) {
                    continue;
                }
                $hit = null;
                foreach ($this->claves($e->nif) as $k) {
                    $hit = $hit ?? ($fuente[$k] ?? null);
                }
                $planCta = null;
                foreach ($this->claves($e->nif) as $k) {
                    $planCta = $planCta ?? ($plan[$tipoPlan][$k] ?? null);
                }
                if (! $hit && ! $planCta) {
                    $sin[] = "{$e->id} | {$e->entidad} | {$e->nif} | {$flag} | estado {$e->estado} | falta en SAGE";
                    continue;
                }
                if ($dupl) {
                    $difs[] = "{$e->id} | {$e->entidad} | {$e->nif} | NIF repetido en Appmos: no lo toco";
                    continue;
                }
                if (($hit && count($hit) > 1) || (! $hit && $planCta && count($planCta) > 1)) {
                    $difs[] = "{$e->id} | {$e->entidad} | {$e->nif} | varias filas en SAGE para este NIF ({$flag}): no lo toco";
                    continue;
                }
                $cod = $hit[0]['cod'] ?? null;
                $cta = $hit[0]['cta'] ?? ($planCta[0]['cta'] ?? null);
                foreach ([$colCod => $cod, $colCta => $cta] as $col => $nuevo) {
                    if ($nuevo === null || $nuevo === '') {
                        continue;
                    }
                    $actual = trim((string) $e->{$col});
                    if ($actual === '') {
                        $cambios[] = [$e, $col, $nuevo];
                        $n[$t]++;
                    } elseif ($actual !== (string) $nuevo) {
                        $difs[] = "{$e->id} | {$e->entidad} | {$e->nif} | {$col}: Appmos {$actual} ≠ SAGE {$nuevo} (no lo toco)";
                    }
                }
            }
        }
        $this->info('A rellenar: '.count($cambios).' campos ('.$n['cli'].' de cliente, '.$n['pro'].' de proveedor) en '.collect($cambios)->pluck(0)->pluck('id')->unique()->count().' entidades');
        foreach ($cambios as [$e, $col, $nuevo]) {
            $this->line("  + {$e->id} | {$e->entidad} | {$col} = {$nuevo}");
        }
        $this->warn('Diferencias / dudas: '.count($difs));
        foreach ($difs as $l) {
            $this->line('  ! '.$l);
        }
        $this->warn('Sin pareja en los ficheros de SAGE: '.count($sin));
        foreach ($sin as $l) {
            $this->line('  ? '.$l);
        }
        if ($this->option('aplicar')) {
            foreach ($cambios as [$e, $col, $nuevo]) {
                \DB::table('entidades')->where('id', $e->id)->whereRaw("coalesce(`$col`,'') = ''")->update([$col => $nuevo]);
            }
            $this->info('Aplicado.');
        } else {
            $this->comment('(informe; con --aplicar se guardan los «+»)');
        }

        return 0;
    }
}
