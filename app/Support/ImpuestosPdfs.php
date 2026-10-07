<?php

namespace App\Support;

use App\Models\ImpuestoDocumento;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * PDF de impuestos (7-oct-2026). Los de OneDrive (carpetas de impuestos de _Clientes y de _RUR_Marta_Alex, ver config
 * contabilidad.impuestos_raices) los sube un PC trabajador (tarea pc.impuestos_pdfs) o el comando impuestos:indexar-pdfs; el servidor
 * los guarda, deduce del nombre (<cliente> M303 1T 2026.pdf) a qué cliente, impuesto y periodo pertenecen y, si es un PDF
 * «presentado», pasa a presentado la casilla que estuviera pendiente (y crea la obligación si el cliente no la tenía).
 */
class ImpuestosPdfs
{
    /** Palabras que convierten un fichero en «otro» documento (justificante, aplazamiento...): no cuenta como el impuesto presentado. */
    protected const OTROS = '/justific|aplaz|concesi|solicitud|recargo|requerimiento|rectificativ|\bnrc\b|\bpago\b(?!\s+(?:fraccionado|a cuenta))|datos fiscales|propuesta|escrito|resoluci|carta de pago|multa|comprobante|memoria/iu';

    /** [alias normalizado => etiqueta] de la última indiceNombres(): qué declaración del cliente es cada nombre de fichero. */
    public static array $etiquetas = [];

    /** Texto sin acentos, minúsculas, sin puntuación y sin la forma societaria final (S.L., SA...): para comparar nombres. */
    public static function normalizar(string $s): string
    {
        $s = Str::lower(Str::ascii($s));
        $t = preg_split('/[^a-z0-9]+/', $s, -1, PREG_SPLIT_NO_EMPTY);
        for ($i = 0; $i < 2; $i++) {
            $n = count($t);
            if ($n >= 3 && $t[$n - 3] === 's' && $t[$n - 2] === 'l' && $t[$n - 1] === 'u') {
                array_splice($t, -3);
            } elseif ($n >= 2 && $t[$n - 2] === 's' && in_array($t[$n - 1], ['l', 'a'], true)) {
                array_splice($t, -2);
            } elseif ($n >= 2 && in_array($t[$n - 1], ['sl', 'slu', 'sa', 'sau', 'sc', 'cb', 'slp', 'scp'], true)) {
                array_pop($t);
            }
        }

        return implode(' ', $t);
    }

    /**
     * Deduce del nombre del fichero: cliente_texto, modelo (303, 111, 200...), ejercicio (año del seguimiento), periodo (T1, 05, P1, A) y tipo.
     * $anioCarpeta: año de la carpeta (para los nombres sin año). Devuelve claves null si no se reconoce.
     */
    public static function parsear(string $nombre, ?int $anioCarpeta = null): array
    {
        $base = preg_replace('/\.[A-Za-z0-9]{2,4}$/', '', $nombre);
        $b = trim(str_replace('_', ' ', $base));
        $r = ['cliente_texto' => $b, 'modelo' => null, 'ejercicio' => null, 'periodo' => null, 'tipo' => 'presentado'];
        $resto = '';
        $anioIS = null;
        // Modelo: «M303», «MOD111», «MODELO 303», «Mod. 200» (también pegado al nombre: «PowerpoolM202») o «IS 2025» / «IS2025» (= modelo 200)
        if (preg_match('/^(.*?)[\s\-,]*(?:\b(?:MOD(?:ELO)?\.?\s?|M\s?)|(?<=[a-zñáéíóú])(?-i:MODELO\s?|MOD\.?\s?|M))(\d{3})(?!\d)(.*)$/iu', $b, $m)) {
            [$r['cliente_texto'], $r['modelo'], $resto] = [trim($m[1], " \t-,."), $m[2], $m[3]];
        } elseif (preg_match('/^(.*?)[\s\-,]*(?:\b|(?<=[a-zñáéíóú])(?-i:(?=IS)))IS\s?(20\d\d)(?!\d)(.*)$/iu', $b, $m)) {
            [$r['cliente_texto'], $r['modelo'], $resto, $anioIS] = [trim($m[1], " \t-,."), '200', $m[3], (int) $m[2]];
        } elseif (preg_match('/^(.*?)[\s\-,]*\bdatos fiscales\b[\s\-,]*(20\d\d)?(.*)$/iu', $b, $m)) {   // datos fiscales del IS: cuelgan del 200
            [$r['cliente_texto'], $r['modelo'], $resto, $anioIS] = [trim($m[1], " \t-,."), '200', $m[3], $m[2] ? (int) $m[2] : null];
            $r['tipo'] = 'otro';
        }
        if (preg_match('/borrador/iu', $b)) {
            $r['tipo'] = 'borrador';
        } elseif ($r['tipo'] === 'presentado' && preg_match(self::OTROS, $b)) {
            $r['tipo'] = 'otro';
        }
        if (! $r['modelo']) {
            $r['tipo'] = $r['tipo'] === 'presentado' ? 'otro' : $r['tipo'];

            return $r;
        }
        $per = null;
        $anio = $anioIS;
        $patT = '/(?<!\d)([1-4])\s?T(?![A-Za-z])/iu';
        $patP = '/(?<!\d)([1-3])\s?PF?(?![A-Za-z])/iu';
        // El periodo suele ir detrás del modelo («M303 1T 2026»), pero a veces delante («ESPIRAL 1P2026 M202»)
        foreach ([$resto, $r['cliente_texto']] as $i => $texto) {
            if (preg_match($patT, $texto, $x)) {
                $per = 'T'.$x[1];
            } elseif (preg_match($patP, $texto, $x)) {
                $per = 'P'.$x[1];
            } elseif ($i === 0 && preg_match('/(?<!\d)(0[1-9]|1[0-2])\s?M(?![A-Za-z])/iu', $texto, $x)) {
                $per = $x[1];
            } elseif ($i === 0 && preg_match('/(?<!\d)(0[1-9]|1[0-2])\s?(20\d\d)(?!\d)/u', $texto, $x)) {
                [$per, $anio] = [$x[1], $anio ?: (int) $x[2]];
            } elseif ($i === 0 && preg_match('/(20\d\d)\s?(0[1-9]|1[0-2])(?!\d)/u', $texto, $x)) {
                [$per, $anio] = [$x[2], $anio ?: (int) $x[1]];
            }
            if ($per) {
                if ($i === 1) {   // quitarlo del nombre del cliente
                    $r['cliente_texto'] = trim(preg_replace('/\s*(?<!\d)[1-4]\s?[TP]F?\s?(?:20\d\d)?(?![A-Za-z])/iu', ' ', $r['cliente_texto']), " \t-,.");
                    $anio ??= preg_match('/(20\d\d)/', $texto, $y) ? (int) $y[1] : null;
                }
                break;
            }
        }
        if (! $anio && preg_match('/[TP]F?\s?(\d\d)(?!\d)/iu', $resto, $x)) {
            $anio = 2000 + (int) $x[1];
        }
        if (! $anio && preg_match('/(?<!\d)(20\d\d)(?!\d)/u', $resto, $x)) {
            $anio = (int) $x[1];
        }
        // «Borrador IS2025 Actualis»: el cliente va detrás del modelo
        $c = trim(preg_replace('/\b(borrador|presentado|modelo)\b/iu', ' ', $r['cliente_texto']), " \t-,.");
        if ($c === '' && $resto !== '') {
            $c = trim(preg_replace(['/(?<!\d)[1-4]\s?[TP]F?\b/iu', '/(?<!\d)(?:20)?\d\d\s?M\b/iu', '/\b20\d\d\b/u', '/\b(borrador|presentado|justificante.*|datos fiscales)\b/iu'], ' ', $resto), " \t-,.+");
        }
        $r['cliente_texto'] = $c;
        $modelo = DB::table('impuesto_modelos')->where('codigo', $r['modelo'])->first();
        if ($modelo && ! $per && $modelo->periodicidad === 'A') {
            $per = 'A';
        }
        $desfase = (int) ($modelo->desfase ?? 0);
        $r['periodo'] = $per;
        // Sin año en el nombre, el de la carpeta ya es el del seguimiento; con año, los modelos con desfase (IS 2025 → ejercicio 2026) suman uno
        $r['ejercicio'] = $anio ? $anio + $desfase : $anioCarpeta;

        return $r;
    }

    /** [nombre normalizado => [ids de entidad]] con nombre, alias y alias de ficheros de cada entidad. */
    public static function indiceNombres(): array
    {
        $idx = [];
        $add = function (string $n, int $id) use (&$idx) {
            $n = self::normalizar($n);
            if (strlen($n) >= 3) {
                $idx[$n][$id] = $id;
            }
        };
        foreach (DB::table('entidades')->get(['id', 'entidad', 'alias']) as $e) {
            $add((string) $e->entidad, $e->id);
            if ($e->alias) {
                $add(str_replace('_', ' ', $e->alias), $e->id);
            }
        }
        self::$etiquetas = [];
        foreach (DB::table('impuesto_alias')->get() as $a) {
            $idx[$a->alias][$a->entidad_id] = $a->entidad_id;
            if ($a->etiqueta !== '') {
                self::$etiquetas[$a->alias] = $a->etiqueta;
            }
        }

        return $idx;
    }

    /** Entidad a la que se refiere el texto del nombre de un fichero (null si no hay ninguna clara). */
    public static function entidadPorTexto(string $texto, ?array $idx = null): ?int
    {
        $c = self::normalizar($texto);
        if ($c === '') {
            return null;
        }
        $idx ??= self::indiceNombres();
        if (isset($idx[$c])) {
            return self::unica(array_values($idx[$c]));
        }
        $cand = [];
        foreach ($idx as $nombre => $ids) {
            if (str_starts_with($nombre, $c.' ') || str_starts_with($c, $nombre.' ')) {
                $cand += $ids;
            }
        }

        return self::unica(array_values($cand));
    }

    /** Un solo id, o el único de las entidades activas si hay varios; si no, null. */
    protected static function unica(array $ids): ?int
    {
        if (count($ids) === 1) {
            return (int) $ids[0];
        }
        if (! $ids) {
            return null;
        }
        $act = DB::table('entidades')->whereIn('id', $ids)->where('estado', 1)->pluck('id');

        return $act->count() === 1 ? (int) $act[0] : null;
    }

    /** Año de la carpeta (o «2026 RMA») en la ruta relativa a OneDrive. */
    public static function anioDeRuta(string $ruta): ?int
    {
        return preg_match('#(?:^|/)[^/]*?(20\d\d)(?:[^/\d][^/]*)?(?=/)#u', $ruta, $m) ? (int) $m[1] : null;
    }

    /**
     * Guarda un PDF de OneDrive ($origen = ruta local del fichero) y lo asocia. $meta: ruta (relativa a OneDrive), tam, mtime.
     * Devuelve el documento.
     */
    public static function registrar(array $meta, string $origen, ?array $idx = null): ImpuestoDocumento
    {
        $sha = hash_file('sha256', $origen);
        $almacen = 'impuestos/docs/'.$sha.'.pdf';
        if (! Storage::disk('local')->exists($almacen)) {
            Storage::disk('local')->put($almacen, fopen($origen, 'rb'));
        }
        $nombre = basename($meta['ruta']);
        $doc = ImpuestoDocumento::firstOrNew(['ruta_origen' => $meta['ruta']]);
        $doc->fill(['nombre' => $nombre, 'almacen' => $almacen, 'tam' => (int) ($meta['tam'] ?? filesize($origen)), 'mtime' => (int) ($meta['mtime'] ?? 0),
            'sha256' => $sha, 'origen' => 'onedrive']);
        $doc->save();
        self::asociar($doc, $idx);

        return $doc;
    }

    /** Deduce y guarda cliente/modelo/ejercicio/periodo/tipo del documento y, si procede, marca la casilla. */
    public static function asociar(ImpuestoDocumento $doc, ?array $idx = null): void
    {
        $p = self::parsear($doc->nombre, $doc->ruta_origen ? self::anioDeRuta($doc->ruta_origen) : null);
        // un documento marcado «otro» (a mano o por el nombre) no vuelve a «presentado» al reasociarlo
        $tipo = $doc->tipo === 'otro' && $p['tipo'] === 'presentado' ? 'otro' : $p['tipo'];
        $doc->fill(['cliente_texto' => $p['cliente_texto'], 'modelo' => $p['modelo'], 'ejercicio' => $p['ejercicio'], 'periodo' => $p['periodo'], 'tipo' => $tipo]);
        $idx ??= self::indiceNombres();
        $doc->entidad_id = self::entidadPorTexto((string) $p['cliente_texto'], $idx);
        $doc->etiqueta = $doc->entidad_id ? (self::$etiquetas[self::normalizar((string) $p['cliente_texto'])] ?? '') : '';
        $doc->save();
        self::aplicar($doc);
    }

    /** Un PDF «presentado» de una casilla: crea la obligación si falta y pasa a presentado lo que estaba pendiente (o sin fila). */
    public static function aplicar(ImpuestoDocumento $doc): void
    {
        if ($doc->quitado_at || $doc->tipo !== 'presentado' || ! $doc->entidad_id || ! $doc->modelo || ! $doc->periodo || ! $doc->ejercicio) {
            return;
        }
        $modelo = DB::table('impuesto_modelos')->where('codigo', $doc->modelo)->first();
        if (! $modelo) {
            return;
        }
        $per = Impuestos::periodicidadDe($doc->periodo);
        $ob = DB::table('entidad_impuestos')->where('entidad_id', $doc->entidad_id)->where('modelo_id', $modelo->id)->where('etiqueta', (string) $doc->etiqueta)->first();
        $ahora = now();
        if (! $ob) {
            $id = DB::table('entidad_impuestos')->insertGetId(['entidad_id' => $doc->entidad_id, 'modelo_id' => $modelo->id, 'etiqueta' => (string) $doc->etiqueta, 'periodicidad' => $per,
                'created_at' => $ahora, 'updated_at' => $ahora]);
            Impuestos::asegurarEjercicio((int) $doc->ejercicio, $id);
            $ob = (object) ['id' => $id, 'periodicidad' => $per];
        }
        if ($ob->periodicidad !== $per) {   // p. ej. un PDF mensual de quien declara por trimestres: no se toca
            return;
        }
        $fila = DB::table('impuesto_estados')->where(['entidad_impuesto_id' => $ob->id, 'ejercicio' => $doc->ejercicio, 'periodo' => $doc->periodo])->first();
        if (! $fila) {
            DB::table('impuesto_estados')->insert(['entidad_impuesto_id' => $ob->id, 'ejercicio' => $doc->ejercicio, 'periodo' => $doc->periodo,
                'estado' => 'presentado', 'created_at' => $ahora, 'updated_at' => $ahora]);
        } elseif (in_array($fila->estado, ['no', 'pendiente'], true)) {
            DB::table('impuesto_estados')->where('id', $fila->id)->update(['estado' => 'presentado', 'updated_at' => $ahora]);
        }
    }

    /** Vuelve a asociar los PDF de OneDrive sin cliente (tras añadir alias o cambiar nombres de entidades). Devuelve cuántos se han asignado. */
    public static function reasociarSinAsignar(): int
    {
        $idx = self::indiceNombres();
        $n = 0;
        foreach (ImpuestoDocumento::whereNull('entidad_id')->whereNull('quitado_at')->where('origen', 'onedrive')->get() as $d) {
            self::asociar($d, $idx);
            $n += $d->entidad_id ? 1 : 0;
        }

        return $n;
    }

    /** Raíces de OneDrive (relativas) con los PDF de impuestos de los años dados: [[ruta con _Clientes o Clientes, año]...]. */
    public static function raices(array $anios): array
    {
        $out = [];
        foreach ($anios as $a) {
            foreach (config('contabilidad.impuestos_raices', []) as $plantilla) {
                $out[] = str_replace('{A}', (string) $a, $plantilla);
            }
        }

        return $out;
    }
}
