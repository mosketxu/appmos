<?php

namespace App\Http\Livewire\Contabilidad;

use App\Models\Entidad;
use App\Models\EntidadHistorico;
use App\Models\Provincia;
use App\Models\Suma;
use App\Support\AltaClienteCorreo;
use App\Support\AltaClienteSage;
use App\Support\GraphMail;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Nuevo cliente (4-nov-2026, Proc.Mensuales; clave nuevo_cliente): de un correo «Alta cliente» de Marta se rellena la ficha de una entidad.
 * El correo se indica de tres maneras: escribiendo (parte de) su asunto o el nombre del cliente, arrastrando el correo desde Outlook (un .msg o
 * .eml: del .msg solo se usa el nombre del fichero, que es el asunto, y se busca en el buzón; el .eml se lee directamente) o eligiéndolo de la lista.
 * No hay nada en segundo plano: solo se mira el buzón cuando se pulsa «Buscar». Antes de crear se enseñan los campos para corregirlos
 * («Alex decide»: los avisos no bloquean, salvo NIF repetido, que la base de datos no admite). Lo que no tiene campo propio (fecha, primera
 * factura, importe, cuota, texto libre de Marta) va al historial de la entidad. Lógica de lectura: App\Support\AltaClienteCorreo.
 */
class NuevoCliente extends Component
{
    use WithFileUploads;

    public const CLAVE = 'nuevo_cliente';

    public string $consulta = '';
    public $fichero = null;
    public array $resultados = [];
    public ?string $mensajeId = null;
    public string $asunto = '';
    public array $campos = [];
    public string $extra = '';
    public array $nota = [];
    public array $avisos = [];
    public string $error = '';
    public ?int $creada = null;
    public bool $confirmarNombre = false;
    /** Código 430xxx propuesto (código de cliente = cuenta contable) y de dónde sale. */
    public array $codigoInfo = [];
    /** Excel de clientes para SAGE ya generado (nombre dentro de storage/app/nuevo-cliente). */
    public string $excel = '';

    protected function puede(): bool
    {
        $u = auth()->user();

        return $u && $u->can('contabilidad.procesosmensuales') && $u->can('proceso.pm.'.self::CLAVE) && $u->can('entidades.editar');
    }

    protected function buzon(): string
    {
        return (string) config('contabilidad.graph.sender');
    }

    public function buscar(bool $asuntoExacto = false): void
    {
        $this->reiniciarCorreo();
        if (! $this->puede() || trim($this->consulta) === '') {
            return;
        }
        if (! GraphMail::configurado()) {
            $this->error = 'Este servidor no tiene las credenciales de correo (Graph); hay que hacerlo en la web de producción.';

            return;
        }
        try {
            $this->resultados = GraphMail::buscarMensajes($this->buzon(), $this->consulta);
        } catch (\Throwable $e) {
            $this->error = 'No he podido buscar en el correo: '.$e->getMessage();

            return;
        }
        if ($asuntoExacto) {
            // Correo arrastrado: Graph busca el texto también en el cuerpo; me quedo con los que tienen ese asunto (sin «RE:»/«FW:» no, tal cual).
            $q = AltaClienteCorreo::norm($this->consulta);
            $exactos = array_values(array_filter($this->resultados, fn ($r) => AltaClienteCorreo::norm($r['asunto']) === $q));
            if ($exactos) {
                $this->resultados = $exactos;
            }
        }
        if (count($this->resultados) === 1) {
            $this->elegir($this->resultados[0]['id']);
        } elseif (! $this->resultados) {
            $this->error = 'No encuentro ningún correo con «'.$this->consulta.'». Prueba con otra parte del asunto o con el nombre del cliente.';
        }
    }

    /** Correo arrastrado/subido. */
    public function updatedFichero(): void
    {
        $this->reiniciarCorreo();
        if (! $this->puede() || ! $this->fichero) {
            return;
        }
        $nombre = $this->fichero->getClientOriginalName();
        $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
        if ($ext === 'eml') {
            $eml = self::leerEml($this->fichero->get());
            $this->fichero = null;
            if ($eml) {
                $this->asunto = $eml['asunto'];
                $this->cargar($eml['html']);

                return;
            }
            $this->error = 'No he podido leer ese .eml.';

            return;
        }
        $this->fichero = null;
        if ($ext !== 'msg') {
            $this->error = 'Arrastra el correo desde Outlook (.msg o .eml).';

            return;
        }
        // .msg: se usa el nombre (Outlook lo nombra con el asunto) para localizar el correo en el buzón.
        $this->consulta = trim(preg_replace('/[_\s]+/', ' ', pathinfo($nombre, PATHINFO_FILENAME)));
        $this->buscar(true);
    }

    public function elegir(string $id): void
    {
        if (! $this->puede()) {
            return;
        }
        try {
            $m = GraphMail::mensaje($this->buzon(), $id);
        } catch (\Throwable $e) {
            $this->error = 'No he podido abrir el correo: '.$e->getMessage();

            return;
        }
        $this->mensajeId = $id;
        $this->asunto = $m['asunto'];
        $this->cargar($m['tipo'] === 'text' ? nl2br(e($m['html'])) : $m['html']);
    }

    public function cargar(string $html): void
    {
        $r = AltaClienteCorreo::interpretar($html);
        // Lo que trae el correo, más los valores con los que Entidades crea una ficha nueva; el resto de campos de la ficha queda para rellenar.
        $this->campos = array_filter($r['campos'], fn ($v) => $v !== null) + [
            'pais_id' => 'ES', 'idioma' => 'ES', 'tipoiva' => '0.21', 'diafactura' => 1, 'diavencimiento' => 10,
            'cliente' => true, 'estado' => 1, 'facturar' => isset($r['campos']['importe']), 'proveedor' => false, 'contacto' => false, 'favorito' => false, 'enviar' => false,
        ];
        $this->extra = $r['extra'];
        $this->nota = $r['nota'];
        $this->avisos = $r['avisos'];
        $this->resultados = [];
        $this->avisos = array_merge($this->avisos, $this->duplicados());
        $this->proponerCodigo();
    }

    /** Código de cliente = cuenta contable: el mayor 430xxx de 6 dígitos del listado de clientes, el plan y las entidades, más 1. */
    public function proponerCodigo(): void
    {
        $r = AltaClienteSage::siguiente();
        $this->codigoInfo = $r;
        if ($this->campos && $r['codigo']) {
            $this->campos['codigo_cliente'] = $r['codigo'];
            $this->campos['cuentacontable'] = $r['codigo'];
        }
    }

    #[On('ficheros-base-cambiados')]
    public function ficherosCambiados(): void
    {
        if ($this->campos) {
            $this->proponerCodigo();
        }
    }

    /** Genera (o repite) el Excel de clientes con la fila de la entidad creada. */
    public function generarExcel(): void
    {
        if (! $this->puede() || ! $this->creada) {
            return;
        }
        try {
            $this->excel = AltaClienteSage::excelAlta(Entidad::withoutGlobalScopes()->findOrFail($this->creada));
            $this->error = '';
        } catch (\Throwable $e) {
            $this->error = 'No he podido preparar el Excel para SAGE: '.$e->getMessage();
        }
    }

    public function descargar()
    {
        $f = AltaClienteSage::dirSalida().'/'.basename($this->excel);
        if (! $this->puede() || $this->excel === '' || ! is_file($f)) {
            return null;
        }

        return response()->download($f, $this->excel);
    }

    /** Avisos si el NIF o el nombre ya están en Entidades. */
    protected function duplicados(): array
    {
        $av = [];
        $nif = AltaClienteCorreo::nifLimpio($this->campos['nif'] ?? '');
        if ($nif !== '' && ($e = $this->porNif($nif))) {
            $av[] = "NIF_REPETIDO|Ya existe una entidad con ese NIF: {$e->entidad} (id {$e->id}).|{$e->id}";
        }
        $nombre = trim((string) ($this->campos['entidad'] ?? ''));
        if ($nombre !== '' && ($e = Entidad::withoutGlobalScopes()->whereRaw('lower(entidad) = ?', [mb_strtolower($nombre)])->first())) {
            $av[] = "NOMBRE_REPETIDO|Ya existe una entidad con ese nombre (id {$e->id}).|{$e->id}";
        }

        return $av;
    }

    protected function porNif(string $nif): ?Entidad
    {
        return Entidad::withoutGlobalScopes()
            ->whereRaw("upper(replace(replace(replace(nif,' ',''),'-',''),'.','')) = ?", [$nif])->first();
    }

    public function crear(): void
    {
        if (! $this->puede() || ! $this->campos) {
            return;
        }
        $c = $this->campos;
        $nombre = trim((string) ($c['entidad'] ?? ''));
        if ($nombre === '') {
            $this->error = 'Falta el nombre del cliente.';

            return;
        }
        $nif = AltaClienteCorreo::nifLimpio($c['nif'] ?? '');
        if ($nif !== '' && ($e = $this->porNif($nif))) {
            $this->error = "Ya existe {$e->entidad} con ese NIF (id {$e->id}); la base de datos no admite dos entidades con el mismo NIF.";

            return;
        }
        $dup = Entidad::withoutGlobalScopes()->whereRaw('lower(entidad) = ?', [mb_strtolower($nombre)])->first();
        if ($dup) {
            $this->error = "Ya existe una entidad llamada «{$dup->entidad}» (id {$dup->id}); el nombre tiene que ser único. Cámbialo (p. ej. añade la ciudad) si es otra.";

            return;
        }
        $v = fn ($k) => ($x = trim((string) ($c[$k] ?? ''))) === '' ? null : $x;
        try {
            $id = DB::transaction(function () use ($c, $v, $nombre, $nif) {
                $datos = ['entidad' => $nombre, 'nif' => $nif ?: null];
                foreach (['direccion', 'codpostal', 'localidad', 'provincia_id', 'pais_id', 'idioma', 'tfno', 'web', 'emailgral', 'emailadm', 'banco1', 'banco2', 'banco3',
                    'iban1', 'iban2', 'iban3', 'referenciacliente', 'cuentacontable', 'codigo_cliente', 'alias', 'cnae', 'epigrafe_iae', 'observaciones',
                    'digitos_cuenta', 'suma_id', 'grupo_id', 'cicloimpuesto_id'] as $k) {
                    $datos[$k] = $v($k);
                }
                $datos['direccion'] = $datos['direccion'] ? mb_substr($datos['direccion'], 0, 100) : null;
                $datos['observaciones'] = $datos['observaciones'] ? mb_substr($datos['observaciones'], 0, 255) : null;
                foreach (['tipoiva' => 0.21, 'diafactura' => 1, 'diavencimiento' => 10, 'metodopago_id' => 1, 'estado' => 1] as $k => $def) {
                    $datos[$k] = is_numeric($c[$k] ?? null) ? $c[$k] + 0 : $def;
                }
                foreach (['porcentajemarta', 'porcentajesusana'] as $k) {
                    $datos[$k] = is_numeric($c[$k] ?? null) ? (float) $c[$k] : 0;
                }
                foreach (['favorito', 'facturar', 'cliente', 'proveedor', 'contacto', 'enviar'] as $k) {
                    $datos[$k] = (bool) ($c[$k] ?? false);
                }
                if (! empty($c['ciclo'])) {
                    $datos['ciclofacturacion_id'] = (int) $c['ciclo'];
                }
                $e = Entidad::create($datos);
                $partes = ['Alta desde el correo «'.$this->asunto.'».'];
                foreach ($this->nota as $etq => $val) {
                    $partes[] = "{$etq}: {$val}.";
                }
                if (trim($this->extra) !== '') {
                    $partes[] = trim($this->extra);
                }
                $importe = $v('importe') !== null ? (float) $c['importe'] : null;
                EntidadHistorico::create([
                    'entidad_id' => $e->id, 'tipo' => 'alta', 'fecha' => now()->toDateString(),
                    'comentario' => mb_substr(implode(' ', $partes), 0, 2000), 'user_id' => auth()->id(),
                    'importe_facturacion' => $importe, 'periodo_facturacion' => $importe !== null && ! empty($c['ciclo']) ? (int) $c['ciclo'] : null,
                ]);

                return $e->id;
            });
        } catch (\Throwable $e) {
            $this->error = 'No se ha podido crear: '.$e->getMessage();

            return;
        }
        $this->creada = $id;
        $this->generarExcel();
        $err = $this->error;
        $this->campos = [];
        $this->avisos = [];
        $this->error = $err;
    }

    public function otro(): void
    {
        $this->reiniciarCorreo();
        $this->consulta = '';
        $this->creada = null;
    }

    protected function reiniciarCorreo(): void
    {
        $this->resultados = [];
        $this->mensajeId = null;
        $this->campos = [];
        $this->avisos = [];
        $this->nota = [];
        $this->extra = '';
        $this->asunto = '';
        $this->error = '';
        $this->creada = null;
        $this->excel = '';
        $this->codigoInfo = [];
    }

    /** Asunto y HTML de un .eml (multipart, quoted-printable o base64). */
    public static function leerEml(string $raw): ?array
    {
        $parte = fn (string $t) => preg_split('/\r?\n\r?\n/', $t, 2) + [1 => ''];
        $cabeceras = function (string $bloque): array {
            $bloque = preg_replace('/\r?\n[ \t]+/', ' ', $bloque);
            $h = [];
            foreach (preg_split('/\r?\n/', $bloque) as $l) {
                if (str_contains($l, ':')) {
                    [$k, $x] = explode(':', $l, 2);
                    $h[strtolower(trim($k))] = trim($x);
                }
            }

            return $h;
        };
        $decodificar = function (array $h, string $cuerpo): string {
            $enc = strtolower($h['content-transfer-encoding'] ?? '');
            $cuerpo = $enc === 'base64' ? base64_decode($cuerpo) : ($enc === 'quoted-printable' ? quoted_printable_decode($cuerpo) : $cuerpo);
            if (preg_match('/charset="?([\w-]+)/i', $h['content-type'] ?? '', $m) && strtolower($m[1]) !== 'utf-8') {
                $cuerpo = @mb_convert_encoding($cuerpo, 'UTF-8', $m[1]) ?: $cuerpo;
            }

            return $cuerpo;
        };
        $buscar = function (string $texto) use (&$buscar, $parte, $cabeceras, $decodificar): array {
            [$cab, $cuerpo] = $parte($texto);
            $h = $cabeceras($cab);
            $tipo = strtolower($h['content-type'] ?? 'text/plain');
            if (str_starts_with($tipo, 'multipart/') && preg_match('/boundary="?([^";\s]+)/i', $h['content-type'], $m)) {
                $html = null;
                $plano = null;
                foreach (preg_split('/--'.preg_quote($m[1], '/').'(?:--)?\r?\n?/', $cuerpo) as $trozo) {
                    if (trim($trozo) === '') {
                        continue;
                    }
                    [$h2, $t2] = $buscar($trozo);
                    $html = $html ?? $h2;
                    $plano = $plano ?? $t2;
                }

                return [$html, $plano];
            }
            if (str_starts_with($tipo, 'text/html')) {
                return [$decodificar($h, $cuerpo), null];
            }
            if (str_starts_with($tipo, 'text/plain')) {
                return [null, nl2br(e($decodificar($h, $cuerpo)))];
            }

            return [null, null];
        };
        [$cab] = $parte($raw);
        $h = $cabeceras($cab);
        [$html, $plano] = $buscar($raw);
        $html = $html ?? $plano;
        if (! $html) {
            return null;
        }
        $asunto = $h['subject'] ?? '';
        if (str_contains($asunto, '=?')) {
            $asunto = mb_decode_mimeheader($asunto);
        }

        return ['asunto' => $asunto, 'html' => $html];
    }

    public function render()
    {
        return view('livewire.contabilidad.nuevo-cliente', [
            'provincias' => Provincia::orderBy('provincia')->pluck('provincia', 'id'),
            'sumas' => Suma::orderBy('nombre')->pluck('nombre', 'id'),
            'paises' => \App\Models\Pais::orderBy('pais')->pluck('pais', 'id'),
            'grupos' => \App\Models\EntidadGrupo::orderBy('nombre')->pluck('nombre', 'id'),
            'puede' => $this->puede(),
            'sumaId' => AltaClienteSage::sumaId(),
        ]);
    }
}
