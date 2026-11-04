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
 *   son errores antiguos de Alex y NO se tienen en cuenta (orden suya, 4-nov-2026);
 * - añade una fila al final de una COPIA del listado de clientes (el original no se toca) para que Alex la lleve a SAGE.
 */
class AltaClienteSage
{
    public const NIF_SUMA = 'B66260076';

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
                if (preg_match('/^430\d{3}$/', $v)) {
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
                ->filter(fn ($v) => preg_match('/^430\d{3}$/', trim((string) $v)))->map(fn ($v) => (int) $v)->max();
            if ($m) {
                $fuentes['entidades.'.$col] = $m;
                $max = max($max, $m);
            }
        }
        if ($max === 0) {
            return ['codigo' => null, 'fuentes' => $fuentes, 'avisos' => $avisos];
        }
        if ($max >= 430999) {
            $avisos[] = 'Se han acabado los códigos 430xxx de 6 dígitos.';

            return ['codigo' => null, 'fuentes' => $fuentes, 'avisos' => $avisos];
        }

        return ['codigo' => (string) ($max + 1), 'fuentes' => $fuentes, 'avisos' => $avisos];
    }

    /** Copia el último listado de clientes de Suma con una fila más para $e. Devuelve el nombre del fichero (dentro de dirSalida()). */
    public static function excelAlta(Entidad $e): string
    {
        $u = self::sumaId() ? FicherosBase::ultimo(self::sumaId(), 'clientes') : null;
        if (! $u) {
            throw new \RuntimeException('Falta subir el listado de clientes de SAGE de Suma (EU_Clientes_GRID).');
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
        $ultima = $hoja->getHighestDataRow();
        $codigo = (string) ($e->codigo_cliente ?: $e->cuentacontable);
        $prov = $e->provincia_id ? (string) Provincia::whereKey($e->provincia_id)->value('provincia') : '';
        $nueva = $ultima + 1;
        $valores = [
            'Código cliente' => $codigo, 'Razón social' => mb_substr($e->entidad, 0, 40), 'Nombre fiscal' => mb_substr($e->entidad, 0, 35), 'Sigla Nación' => 'ES',
            'CIF/DNI' => mb_substr((string) $e->nif, 0, 13), 'Delegación' => '', 'Teléfono' => mb_substr((string) $e->tfno, 0, 15), 'Municipio' => mb_substr((string) $e->localidad, 0, 25),
            'Provincia' => mb_substr(mb_strtoupper($prov), 0, 20), 'Código comisionista' => '0', 'Código contable' => $codigo, 'Categoría' => 'CLI', 'Nación' => 'ESPAÑA', 'Código Nación' => '108',
        ];
        foreach ($cab as $nombre => $col) {
            $v = $valores[$nombre] ?? '';
            $hoja->getStyle($col.$nueva)->applyFromArray([])->getNumberFormat()->setFormatCode($hoja->getStyle($col.$ultima)->getNumberFormat()->getFormatCode());
            $hoja->getCell($col.$nueva)->setValueExplicit($v, DataType::TYPE_STRING);
        }
        @mkdir(self::dirSalida(), 0775, true);
        $nombre = date('Ymd-His').' '.preg_replace('/\.xlsx$/i', '', preg_replace('/^\d{8}-\d{6}_/', '', $u['nombre'])).' + '.$codigo.'.xlsx';
        IOFactory::createWriter($libro, 'Xlsx')->save(self::dirSalida().'/'.$nombre);

        return $nombre;
    }
}
