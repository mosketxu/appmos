<?php

namespace App\Support;

use App\Models\Entidad;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

/**
 * Cifras de las cuentas de SAGE de una empresa (Entidades → «Nº Dígitos Sage»; por defecto 6, Fashion 7, Neteges 9).
 * Cuando entra un mayor, un plan o un listado de proveedores/clientes se mira el largo real de sus cuentas y, si no coincide con
 * Entidades (o no estaba informado), se corrige Entidades y el cliente.json de Facturas OCR AVISANDO (12-nov-2026, Alex).
 */
class DigitosCuenta
{
    /** Columna donde está el código de cuenta, según el tipo de fichero base. */
    private const CABECERAS = ['Código cuenta', 'CodigoClienteProveedor', 'Código', 'Cuenta'];

    /** Largo más común (6-12) de las cuentas del fichero, o null si no se puede saber. Solo lee las primeras filas. */
    public static function detectar(string $ruta, int $filas = 3000): ?int
    {
        try {
            $filtro = new class($filas) implements IReadFilter {
                public function __construct(private int $max) {}

                public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
                {
                    return $row <= $this->max;
                }
            };
            $lector = IOFactory::createReaderForFile($ruta);
            $lector->setReadDataOnly(true);
            $lector->setReadFilter($filtro);
            $hoja = $lector->load($ruta)->getSheet(0);
            $col = null;
            $cabecera = 0;
            foreach ($hoja->getRowIterator(1, 15) as $fila) {
                foreach ($fila->getCellIterator() as $c) {
                    if (in_array(trim((string) $c->getValue()), self::CABECERAS, true)) {
                        $col = $c->getColumn();
                        $cabecera = $fila->getRowIndex();
                        break 2;
                    }
                }
            }
            if ($col === null) {
                return null;
            }
            $largos = [];
            for ($r = $cabecera + 1; $r <= min($hoja->getHighestRow(), $filas); $r++) {
                $v = preg_replace('/\D/', '', (string) $hoja->getCell($col.$r)->getValue());
                if ($v !== '' && strlen($v) >= 6 && strlen($v) <= 12) {
                    $largos[strlen($v)] = ($largos[strlen($v)] ?? 0) + 1;
                }
            }
            arsort($largos);

            return $largos ? (int) array_key_first($largos) : null;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /** Pone las cifras reales en Entidades y en el cliente.json de Facturas OCR de esa entidad. Devuelve el aviso a mostrar, o '' si ya coincidía. */
    public static function aplicar(int $entidadId, int $real): string
    {
        $e = Entidad::find($entidadId);
        if (! $e || $real < 6 || $real > 12) {
            return '';
        }
        $antes = (int) $e->digitos_cuenta;
        if ($antes !== $real) {
            $e->digitos_cuenta = $real;
            $e->save();
        }
        $dir = rtrim((string) config('contabilidad.facturasocr_dir'), '/');
        foreach (glob($dir.'/*/cliente.json') ?: [] as $f) {
            $cfg = json_decode((string) @file_get_contents($f), true);
            if (is_array($cfg) && (int) ($cfg['entidad_id'] ?? 0) === $entidadId && (int) ($cfg['digitos_cuenta'] ?? 0) !== $real) {
                $cfg['digitos_cuenta'] = $real;
                @file_put_contents($f, json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }
        }
        if ($antes === $real) {
            return '';
        }

        return $antes > 0
            ? "Las cuentas de este fichero tienen {$real} cifras y Entidades decía {$antes}: lo he corregido a {$real} (Nº Dígitos Sage). Revísalo."
            : "Entidades no tenía informado el Nº Dígitos Sage: he puesto {$real} (las cuentas del fichero). Revísalo.";
    }
}
