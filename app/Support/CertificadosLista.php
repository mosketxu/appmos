<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Certificados por caducar: cruza los escaneos de los PCs (Contabilidad/ProcesosMensuales/Certificados/certificados.py,
 * misma lógica en Python para uso por consola). En los PCs los escaneos son los ficheros de OneDrive/_Clientes/_Certificados;
 * en la web, la tabla certificados_escaneos (se sube al escanear cada PC).
 */
class CertificadosLista
{
    /** Quién ve los escaneos de todos los usuarios: Admin y Marta; el resto solo los de su propio PC. */
    public static function verTodos(?\App\Models\User $u): bool
    {
        return $u && (strtolower((string) $u->email) === 'marta.ruiz@sumaempresa.com' || $u->hasRole('Admin'));
    }

    /** [pc => ['escaneado' => ..., 'certs' => [...]]]. $soloUser: solo el escaneo hecho desde el PC de ese usuario. */
    public static function escaneos(?int $soloUser = null): array
    {
        $r = [];
        if ($soloUser !== null) {
            foreach (DB::table('certificados_escaneos')->where('user_id', $soloUser)->get() as $e) {
                $r[$e->pc] = ['escaneado' => $e->escaneado, 'certs' => json_decode($e->certs, true) ?: []];
            }
            return $r;
        }
        if (config('contabilidad.ejecucion_local')) {
            foreach (['e', 'f', 'd'] as $u) {
                foreach (['_Clientes', 'Clientes'] as $c) {
                    foreach (glob("/mnt/{$u}/OneDrive/{$c}/_Certificados/certificados_*.json") ?: [] as $f) {
                        $d = json_decode((string) file_get_contents($f), true);
                        if (is_array($d) && isset($d['pc'])) {
                            $r[$d['pc']] = ['escaneado' => $d['escaneado'], 'certs' => $d['certs']];
                        }
                    }
                }
            }
            if ($r) {
                return $r;
            }
        }
        foreach (DB::table('certificados_escaneos')->get() as $e) {
            $r[$e->pc] = ['escaneado' => $e->escaneado, 'certs' => json_decode($e->certs, true) ?: []];
        }
        return $r;
    }

    public static function nombrePc(string $pc): string
    {
        return ['ALEXMINIPC' => 'AlexMiniPC', 'PORTALEXOMEN' => 'PortalExomen', 'MINISERV' => 'miniServ'][$pc] ?? $pc;
    }

    /**
     * Color de un certificado según su caducidad, por meses naturales: 'rojo' = ya caducado, este mes o el próximo;
     * 'amarillo' = el tercer o el cuarto mes contando el actual; '' = el resto.
     */
    public static function color(string $caduca): string
    {
        $hoy = now();
        $c = \Carbon\Carbon::parse($caduca);
        $meses = ($c->year - $hoy->year) * 12 + ($c->month - $hoy->month);

        return $meses <= 1 ? 'rojo' : ($meses <= 3 ? 'amarillo' : '');
    }

    /**
     * Todos los certificados de los escaneos, sin límite de fecha y por fecha de caducidad. Cada certificado (misma huella)
     * sale una vez con los PCs donde está instalado. Los que ya tienen uno posterior de la misma clave (renovados) salen sin color.
     * [['nombre','alias','cif','caduca','pcs'=>[...],'color','renovado'], ...]
     */
    public static function todos(array $escaneos): array
    {
        $porHuella = [];
        $maxClave = [];
        foreach ($escaneos as $pc => $e) {
            foreach ($e['certs'] as $c) {
                $h = $c['huella'];
                $porHuella[$h] ??= $c + ['pcs' => []];
                $porHuella[$h]['pcs'][] = self::nombrePc($pc);
                $maxClave[$c['clave']] = max($maxClave[$c['clave']] ?? '', $c['caduca']);
            }
        }
        $r = [];
        foreach ($porHuella as $c) {
            $renovado = $c['caduca'] < $maxClave[$c['clave']];
            $pcs = array_values(array_unique($c['pcs']));
            sort($pcs);
            $r[] = ['nombre' => $c['nombre'], 'alias' => $c['alias'] ?? '', 'cif' => preg_replace('/^(emp|per):/', '', $c['clave']), 'caduca' => $c['caduca'],
                'pcs' => $pcs, 'renovado' => $renovado, 'color' => $renovado ? '' : self::color($c['caduca'])];
        }
        usort($r, fn ($a, $b) => [$a['caduca'], $a['nombre']] <=> [$b['caduca'], $b['nombre']]);

        return $r;
    }

    public static function calcular(array $escaneos, int $meses = 3): array
    {
        $hoy = now()->format('Y-m-d');
        $lim = now()->addDays((int) round(30.44 * $meses))->format('Y-m-d');
        $porClave = [];
        foreach ($escaneos as $pc => $e) {
            foreach ($e['certs'] as $c) {
                $c['pc'] = $pc;
                $porClave[$c['clave']][] = $c;
            }
        }
        $res = ['hasta' => $lim, 'pcs' => array_map(fn ($e) => $e['escaneado'], $escaneos), 'lista' => [], 'contradicciones' => [], 'renovados' => []];
        foreach ($porClave as $grupo) {
            $max = max(array_column($grupo, 'caduca'));
            $porH = [];
            foreach ($grupo as $c) {
                $porH[$c['huella']][] = $c;
            }
            foreach ($porH as $cs) {
                $cad = $cs[0]['caduca'];
                if ($cad > $lim) {
                    continue;
                }
                $en = array_values(array_unique(array_column($cs, 'pc')));
                sort($en);
                $item = ['clave' => $cs[0]['clave'], 'nombre' => $cs[0]['nombre'], 'alias' => $cs[0]['alias'], 'caduca' => $cad, 'pcs' => $en, 'caducado' => $cad < $hoy];
                if ($max > $cad) {
                    $nuevo = array_values(array_unique(array_column(array_filter($grupo, fn ($c) => $c['caduca'] === $max), 'pc')));
                    sort($nuevo);
                    $item['renovado_en'] = $nuevo;
                    $item['nuevo_caduca'] = $max;
                    $falta = array_values(array_diff($en, $nuevo));
                    if ($falta) {
                        $item['falta_nuevo_en'] = $falta;
                        $res['contradicciones'][] = $item;
                    } else {
                        $res['renovados'][] = $item;
                    }
                } else {
                    $res['lista'][] = $item;
                }
            }
        }
        usort($res['lista'], fn ($a, $b) => strcmp($a['caduca'], $b['caduca']));
        return $res;
    }
}
