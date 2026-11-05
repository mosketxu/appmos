<?php

namespace App\Support;

use App\Models\Entidad;
use App\Models\Provincia;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Alta de un cliente de Suma en SAGE (4-nov-2026, «Nuevo cliente»). Con los ficheros base de la propia Suma (listado de clientes EU_Clientes_GRID y
 * plan de cuentas, subidos en el recuadro de «Ficheros base»):
 * - código de cliente = cuenta contable = el mayor código de 6 dígitos 430xxx de los dos ficheros (y de las entidades) + 1. Los de otra longitud (7 dígitos)
 *   son errores antiguos de Alex y NO se tienen en cuenta, ni los 430900 en adelante (excepciones); se parte de ~430274 (orden suya, 4-nov-2026);
 * - añade una fila al final de una COPIA del listado de clientes (el original no se toca) para que Alex la lleve a SAGE.
 */
class AltaClienteSage
{
    public const NIF_SUMA = 'B66260076';

    /** Los 430900 en adelante son excepciones (cuentas especiales de Alex): no cuentan para el siguiente código ni se llega a ellos. */
    public const TOPE = 430900;

    public static function sumaId(): int
    {
        return (int) Entidad::withoutGlobalScopes()->where('nif', self::NIF_SUMA)->value('id');
    }

    public static function dirSalida(): string
    {
        return storage_path('app/nuevo-cliente');
    }

    /** @return int[] códigos 430xxx (6 dígitos) que hay en las columnas indicadas (por cabecera) del Excel. */
    protected static function codigos(string $ruta, array $cabeceras): array
    {
        $lector = IOFactory::createReaderForFile($ruta);
        $lector->setReadDataOnly(true);
        $filas = $lector->load($ruta)->getActiveSheet()->toArray(null, false, false, false);
        $cols = [];
        foreach (($filas[0] ?? []) as $i => $h) {
            if (in_array(trim((string) $h), $cabeceras, true)) {
                $cols[] = $i;
            }
        }
        $out = [];
        foreach ($filas as $f) {
            foreach ($cols as $i) {
                $v = trim((string) ($f[$i] ?? ''));
                if (preg_match('/^430\d{3}$/', $v) && (int) $v < self::TOPE) {
                    $out[] = (int) $v;
                }
            }
        }

        return $out;
    }

    /** @return array{codigo: ?string, fuentes: array, avisos: array} */
    public static function siguiente(): array
    {
        $eid = self::sumaId();
        $max = 0;
        $fuentes = [];
        $avisos = [];
        if (! $eid) {
            return ['codigo' => null, 'fuentes' => [], 'avisos' => ['No encuentro la entidad de Suma (NIF '.self::NIF_SUMA.') para sus ficheros base.']];
        }
        foreach (['clientes' => ['Código cliente', 'Código contable'], 'plan' => ['Código cuenta']] as $tipo => $cab) {
            $u = FicherosBase::ultimo($eid, $tipo);
            if (! $u) {
                $avisos[] = 'Falta subir el '.($tipo === 'plan' ? 'plan de cuentas' : 'listado de clientes').' de SAGE de Suma.';
                continue;
            }
            try {
                $c = self::codigos($u['ruta'], $cab);
            } catch (\Throwable $e) {
                $avisos[] = 'No he podido leer «'.$u['nombre'].'»: '.$e->getMessage();
                continue;
            }
            $m = $c ? max($c) : 0;
            $fuentes[$tipo] = $m;
            $max = max($max, $m);
        }
        // También lo ya dado de alta en Appmos y aún no importado en SAGE
        foreach (['codigo_cliente', 'cuentacontable'] as $col) {
            $m = Entidad::withoutGlobalScopes()->whereNotNull($col)->where($col, 'like', '430%')->pluck($col)
                ->filter(fn ($v) => preg_match('/^430\d{3}$/', trim((string) $v)) && (int) $v < self::TOPE)->map(fn ($v) => (int) $v)->max();
            if ($m) {
                $fuentes['entidades.'.$col] = $m;
                $max = max($max, $m);
            }
        }
        if ($max === 0) {
            return ['codigo' => null, 'fuentes' => $fuentes, 'avisos' => $avisos];
        }
        if ($max + 1 >= self::TOPE) {
            $avisos[] = 'Se han acabado los códigos 430xxx antes de las excepciones (430900).';

            return ['codigo' => null, 'fuentes' => $fuentes, 'avisos' => $avisos];
        }

        return ['codigo' => (string) ($max + 1), 'fuentes' => $fuentes, 'avisos' => $avisos];
    }

    /** Entidades dadas de alta con «Nuevo cliente» que todavía no están en el último listado de clientes de SAGE (ni por código ni por CIF), por código. */
    public static function pendientes(): \Illuminate\Support\Collection
    {
        $eid = self::sumaId();
        $u = $eid ? FicherosBase::ultimo($eid, 'clientes') : null;
        $codigos = [];
        $cifs = [];
        if ($u) {
            $lector = IOFactory::createReaderForFile($u['ruta']);
            $lector->setReadDataOnly(true);
            $filas = $lector->load($u['ruta'])->getActiveSheet()->toArray(null, false, false, false);
            $cab = array_map(fn ($h) => trim((string) $h), $filas[0] ?? []);
            $ic = array_search('Código cliente', $cab, true);
            $ik = array_search('Código contable', $cab, true);
            $in = array_search('CIF/DNI', $cab, true);
            foreach (array_slice($filas, 1) as $f) {
                foreach ([$ic, $ik] as $i) {
                    if ($i !== false && trim((string) ($f[$i] ?? '')) !== '') {
                        $codigos[trim((string) $f[$i])] = true;
                    }
                }
                if ($in !== false && ($c = AltaClienteCorreo::nifLimpio((string) ($f[$in] ?? ''))) !== '') {
                    $cifs[$c] = true;
                }
            }
        }
        $ids = \Illuminate\Support\Facades\DB::table('entidad_historico')->where('tipo', 'alta')->pluck('entidad_id');

        return Entidad::withoutGlobalScopes()->whereIn('id', $ids)->get()
            ->filter(fn ($e) => ($c = trim((string) ($e->codigo_cliente ?: $e->cuentacontable))) !== ''
                && ! isset($codigos[$c]) && ! isset($cifs[AltaClienteCorreo::nifLimpio((string) $e->nif)]))
            ->sortBy(fn ($e) => (string) ($e->codigo_cliente ?: $e->cuentacontable))->values();
    }

    /** Copia el último listado de clientes de Suma con UNA FILA MÁS por cada entidad pendiente (todas las nuevas, no solo la última). Devuelve el nombre del fichero (dentro de dirSalida()). */
    public static function excelPendientes(): string
    {
        $u = self::sumaId() ? FicherosBase::ultimo(self::sumaId(), 'clientes') : null;
        if (! $u) {
            throw new \RuntimeException('Falta subir el listado de clientes de SAGE de Suma (EU_Clientes_GRID).');
        }
        $ents = self::pendientes();
        if ($ents->isEmpty()) {
            throw new \RuntimeException('No hay clientes nuevos pendientes: todos los dados de alta ya están en el listado de SAGE.');
        }
        $libro = IOFactory::load($u['ruta']);
        $hoja = $libro->getActiveSheet();
        $cab = [];
        foreach ($hoja->getRowIterator(1, 1) as $fila) {
            foreach ($fila->getCellIterator() as $celda) {
                $cab[trim((string) $celda->getValue())] = $celda->getColumn();
            }
        }
        foreach (['Código cliente', 'Razón social', 'CIF/DNI', 'Código contable'] as $obligada) {
            if (! isset($cab[$obligada])) {
                throw new \RuntimeException("El listado de clientes no tiene la columna «{$obligada}».");
            }
        }
        $nueva = $hoja->getHighestDataRow();
        foreach ($ents as $e) {
            $ultima = $nueva;
            $nueva++;
            $codigo = (string) ($e->codigo_cliente ?: $e->cuentacontable);
            $prov = $e->provincia_id ? (string) Provincia::whereKey($e->provincia_id)->value('provincia') : '';
            $valores = [
                'Código cliente' => $codigo, 'Razón social' => mb_substr($e->entidad, 0, 40), 'Nombre fiscal' => mb_substr($e->entidad, 0, 35), 'Sigla Nación' => 'ES',
                'CIF/DNI' => mb_substr((string) $e->nif, 0, 13), 'Delegación' => '', 'Teléfono' => mb_substr((string) $e->tfno, 0, 15), 'Municipio' => mb_substr((string) $e->localidad, 0, 25),
                'Provincia' => mb_substr(mb_strtoupper($prov), 0, 20), 'Código comisionista' => '0', 'Código contable' => $codigo, 'Categoría' => 'CLI', 'Nación' => 'ESPAÑA', 'Código Nación' => '108',
            ];
            foreach ($cab as $nombre => $col) {
                $hoja->getStyle($col.$nueva)->getNumberFormat()->setFormatCode($hoja->getStyle($col.$ultima)->getNumberFormat()->getFormatCode());
                $hoja->getCell($col.$nueva)->setValueExplicit($valores[$nombre] ?? '', DataType::TYPE_STRING);
            }
        }
        @mkdir(self::dirSalida(), 0775, true);
        $nombre = date('Ymd-His').' '.preg_replace('/\.xlsx$/i', '', preg_replace('/^\d{8}-\d{6}_/', '', $u['nombre'])).' + '.$ents->count().' nuevos.xlsx';
        IOFactory::createWriter($libro, 'Xlsx')->save(self::dirSalida().'/'.$nombre);

        return $nombre;
    }

    /** Código de municipio del INE tal como lo pide SAGE (provincia*1000+municipio, p. ej. Barcelona = 8019), por provincia y nombre; '' si no se encuentra. */
    public static function codigoMunicipio(?string $provinciaId, ?string $localidad): string
    {
        $prov = ltrim((string) $provinciaId, '0');
        $n = self::normMun((string) $localidad);
        $ruta = resource_path('data/municipios_ine.csv');
        if ($prov === '' || $n === '' || ! is_file($ruta)) {
            return '';
        }
        $fh = fopen($ruta, 'r');
        while (($f = fgetcsv($fh, 0, ';', '"', '')) !== false) {
            if (ltrim($f[0], '0') !== $prov) {
                continue;
            }
            $nombre = (string) $f[2];
            $variantes = [$nombre];
            foreach (explode('/', $nombre) as $parte) {
                $variantes[] = $parte;
            }
            foreach ($variantes as $v) {
                if (preg_match('/^(.+), (el|la|los|las|l\'|a|o|os|as|els|les|sa|es|ses|na)$/iu', trim($v), $m)) {
                    $variantes[] = $m[2].' '.$m[1];
                }
            }
            foreach ($variantes as $v) {
                if (self::normMun($v) === $n) {
                    fclose($fh);

                    return (string) ((int) $f[0] * 1000 + (int) $f[1]);
                }
            }
        }
        fclose($fh);

        return '';
    }

    protected static function normMun(string $t): string
    {
        return trim(preg_replace('/\s+/', ' ', str_replace("'", "' ", AltaClienteCorreo::norm($t))));
    }

    /**
     * Excel de importación «Gestión de clientes y proveedores» de SAGE (5-nov-2026): COPIA de la plantilla base (GES_ClientesProveedores, sin sus filas de ejemplo)
     * con una fila por cada entidad pendiente (+ las de $codigosExtra, ya creadas, p. ej. para repetir una prueba). $tipo: C cliente / P proveedor.
     * Devuelve el nombre del fichero (dentro de dirSalida()).
     */
    public static function excelGestion(string $tipo = 'C', string $codigosExtra = ''): string
    {
        $tipo = strtoupper($tipo) === 'P' ? 'P' : 'C';
        $suma = Entidad::withoutGlobalScopes()->find(self::sumaId());
        $u = $suma ? FicherosBase::ultimo($suma->id, 'gestion') : null;
        if (! $u) {
            throw new \RuntimeException('Falta subir la plantilla de importación de SAGE (GES_ClientesProveedores) en «Ficheros base».');
        }
        $ents = self::pendientes()->keyBy('id');
        $extra = array_filter(array_map('trim', preg_split('/[\s,;]+/', $codigosExtra)));
        if ($extra) {
            foreach (Entidad::withoutGlobalScopes()->get() as $e) {
                if (in_array(trim((string) ($e->codigo_cliente ?: $e->cuentacontable)), $extra, true)) {
                    $ents[$e->id] = $e;
                }
            }
        }
        $ents = $ents->sortBy(fn ($e) => (string) ($e->codigo_cliente ?: $e->cuentacontable))->values();
        if ($ents->isEmpty()) {
            throw new \RuntimeException('No hay clientes nuevos pendientes (y no has indicado ningún código para añadir).');
        }
        $libro = IOFactory::load($u['ruta']);
        $hoja = $libro->getActiveSheet();
        $cab = [];
        foreach ($hoja->getRowIterator(1, 1) as $fila) {
            foreach ($fila->getCellIterator() as $celda) {
                $cab[trim((string) $celda->getValue())] = $celda->getColumn();
            }
        }
        foreach (['CodigoEmpresa', 'ClienteOProveedor', 'CodigoClienteProveedor', 'CifDni', 'RazonSocial', 'Domicilio'] as $obligada) {
            if (! isset($cab[$obligada])) {
                throw new \RuntimeException("La plantilla no tiene la columna «{$obligada}».");
            }
        }
        if ($hoja->getHighestDataRow() > 1) {
            $hoja->removeRow(2, $hoja->getHighestDataRow() - 1); // las filas de ejemplo de la plantilla no se usan
        }
        $fila = 1;
        foreach ($ents as $e) {
            $fila++;
            $codigo = trim((string) ($e->codigo_cliente ?: $e->cuentacontable));
            $nif = AltaClienteCorreo::nifLimpio((string) $e->nif);
            $cp = preg_replace('/\D/', '', (string) $e->codpostal);
            $prov = $e->provincia_id ? (string) Provincia::whereKey($e->provincia_id)->value('provincia') : '';
            $provCod = strlen($cp) === 5 ? substr($cp, 0, 2) : (string) $e->provincia_id; // «los dos primeros dígitos del CP»
            $ret = (float) $e->retencion;
            $emailAdm = trim((string) preg_split('/[;,\s]+/', (string) $e->emailadm)[0]);
            $n = fn ($v) => [$v, DataType::TYPE_NUMERIC];
            $t = fn ($v) => [(string) $v, DataType::TYPE_STRING];
            $v = [
                'CodigoEmpresa' => $suma->codigo_empresa_sage ? $n((int) $suma->codigo_empresa_sage) : $t(''),
                'ClienteOProveedor' => $t($tipo), 'CodigoClienteProveedor' => $codigo !== '' ? $n((int) $codigo) : $t(''),
                'SiglaNacion' => $t('ES'), 'CifDni' => $t($nif), 'CodigoCategoriaCliente_' => $t($tipo === 'P' ? 'PRO' : 'CLI'),
                'CifEuropeo' => $t($nif !== '' ? 'ES'.$nif : ''), 'RazonSocial' => $t(mb_substr((string) $e->entidad, 0, 40)),
                'Domicilio' => $t((string) $e->direccion), 'CodigoPostal' => $cp !== '' ? $n((int) $cp) : $t(''),
                'CodigoMunicipio' => ($m = self::codigoMunicipio($provCod, $e->localidad)) !== '' ? $n((int) $m) : $t(''),
                'Municipio' => $t(mb_strtoupper((string) $e->localidad)), 'CodigoProvincia' => $provCod !== '' ? $n((int) $provCod) : $t(''),
                'Provincia' => $t(mb_strtoupper($prov)), 'CodigoNacion' => $n(108), 'Nacion' => $t('ESPAÑA'),
                'Telefono' => $t((string) $e->tfno), 'Email1' => $t(trim((string) $e->emailgral) ?: $emailAdm),
                'IBAN' => $t(preg_replace('/\s+/', '', (string) $e->iban1)),
                'NumeroPlazos' => $n(1), 'DiasFijos1' => $n((int) ($e->diavencimiento ?: 10)),
                'CodigoCuenta' => $codigo !== '' ? $n((int) $codigo) : $t(''),
                'CodigoIdioma_' => $t(strtoupper((string) ($e->idioma ?: 'ES'))), 'CodigoIva' => $n(21),
                'CodigoRetencion' => $ret >= 15 ? $n(18) : ($ret > 0 ? $n((int) round($ret)) : $t('')),
                'CodigoTransaccion' => $n((int) $e->metodopago_id === 2 ? 2 : 1), 'Deducible' => $n(-1),
                'PersonaFisicaJuridica' => $t($e->persona_fj === 'F' ? 'F' : 'J'),
            ];
            foreach ($v as $nombre => [$valor, $tipoDato]) {
                if (isset($cab[$nombre])) {
                    $hoja->getCell($cab[$nombre].$fila)->setValueExplicit($valor, $tipoDato);
                }
            }
        }
        @mkdir(self::dirSalida(), 0775, true);
        $nombre = date('Ymd-His').' GES_ClientesProveedores '.$tipo.' + '.$ents->count().' nuevos.xlsx';
        IOFactory::createWriter($libro, 'Xlsx')->save(self::dirSalida().'/'.$nombre);

        return $nombre;
    }
}
