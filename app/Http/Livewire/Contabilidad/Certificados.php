<?php

namespace App\Http\Livewire\Contabilidad;

use App\Support\CertificadosLista;
use App\Support\GraphMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Livewire\Component;

/**
 * Certificados por caducar (2-oct-2026): se puede lanzar en la web y en local. Los certificados están instalados
 * en los PCs, así que solo el escaneo es local; cada escaneo se sube a la web (tabla certificados_escaneos). Paso 1: «Escanear este PC» (cada PC deja su fichero en OneDrive/_Clientes/_Certificados)
 * y «Calcular lista»: los que caducan en N meses, sin los ya renovados, avisando de las contradicciones
 * (renovado en un PC y no en el otro). La lista se edita (quitar / añadir filas). Paso 2: correo con la lista,
 * destinatario editable (por defecto Marta Ruiz), con confirmación. Código en Contabilidad/ProcesosMensuales/Certificados.
 */
class Certificados extends Component
{
    public int $meses = 3;
    /** Filas editables: [nombre, caduca, pcs, incluir, nota] */
    public array $filas = [];
    public array $contradicciones = [];
    public array $renovados = [];
    public array $escaneos = [];
    public string $salida = '';
    public bool $calculada = false;

    public string $para = 'marta.ruiz@sumaempresa.com';
    public string $cc = '';
    public string $asunto = '';
    public string $intro = "Marta,\n\nEstos son los certificados digitales que tengo yo instalados y que caducan en los próximos meses.";
    public bool $confirmar = false;
    public string $enviado = '';

    /** Año del detalle de envíos y envío abierto para revisar. */
    public int $anio = 0;
    public ?int $verEnvio = null;

    // Fila nueva a mano
    public string $aNombre = '';
    public string $aCaduca = '';

    /** Dentro de Proc.Mensuales (sin menú ni título propios). */
    public bool $embebido = false;

    public function mount(bool $embebido = false): void
    {
        $this->embebido = $embebido;
        $this->anio = (int) now()->format('Y');
        $this->asunto = 'Certificados digitales que caducan - '.now()->locale('es')->translatedFormat('F Y');
        $this->calcular();
    }

    protected function dir(): string
    {
        foreach (['e', 'f', 'd'] as $u) {
            if (is_dir("/mnt/{$u}/Claude/Contabilidad/ProcesosMensuales/Certificados")) {
                return "/mnt/{$u}/Claude/Contabilidad/ProcesosMensuales/Certificados";
            }
        }
        return '/mnt/e/Claude/Contabilidad/ProcesosMensuales/Certificados';
    }

    protected function py(array $args): array
    {
        $r = Process::path($this->dir())->timeout(150)
            ->env(['WSL_INTEROP' => '/run/WSL/1_interop', 'HOME' => '/tmp'])
            ->run(['python3', 'certificados.py', ...$args]);
        return [$r->successful(), trim($r->output()."\n".$r->errorOutput())];
    }

    public function escanear(): void
    {
        if (! config('contabilidad.ejecucion_local')) {
            $this->salida = '⚠ El escaneo solo se hace desde AlexMiniPC o PortalExomen (los certificados están en el PC).';
            return;
        }
        [$ok, $out] = $this->py(['--escanear']);
        $this->salida = ($ok ? '✅ ' : '⚠ ').$out;
        if ($ok) {
            $this->salida .= $this->subirEscaneo();
        }
        $this->calcular();
    }

    /** Sube a la web el escaneo de este PC (el fichero que acaba de dejar certificados.py). */
    protected function subirEscaneo(): string
    {
        $url = config('contabilidad.certificados_sync_url');
        $token = (string) config('contabilidad.certificados_sync_token');
        if (! $url || $token === '') {
            return "\n⚠ No se ha subido a la web: falta CERTIFICADOS_SYNC_TOKEN en el .env de este PC.";
        }
        $mio = null;
        foreach (CertificadosLista::escaneos() as $pc => $e) {
            if ($mio === null || $e['escaneado'] > $mio[1]['escaneado']) {
                $mio = [$pc, $e];
            }
        }
        if (! $mio) {
            return '';
        }
        try {
            $r = Http::withHeaders(['X-Token' => $token])->timeout(30)->post($url, ['pc' => $mio[0], 'escaneado' => $mio[1]['escaneado'], 'certs' => $mio[1]['certs']]);
            return $r->successful() ? "\n🌐 Subido a la web ({$mio[0]})." : "\n⚠ La web no ha aceptado el escaneo (HTTP {$r->status()}).";
        } catch (\Throwable $e) {
            return "\n⚠ No se ha podido subir a la web: ".$e->getMessage();
        }
    }

    public function calcular(): void
    {
        $this->enviado = '';
        $this->confirmar = false;
        $d = CertificadosLista::calcular(CertificadosLista::escaneos(), max(1, $this->meses));
        $this->calculada = true;
        $this->escaneos = $d['pcs'];
        $this->contradicciones = $d['contradicciones'];
        $this->renovados = $d['renovados'];
        $this->filas = array_map(fn ($i) => ['nombre' => $i['nombre'].($i['alias'] ? ' ('.$i['alias'].')' : ''),
            'caduca' => $i['caduca'], 'pcs' => implode(' + ', array_map(fn ($p) => $p === 'ALEXMINIPC' ? 'AlexMiniPC' : 'PortalExomen', $i['pcs'])),
            'incluir' => true, 'nota' => $i['caducado'] ? 'ya caducado' : ''], $d['lista']);
    }

    public function anadir(): void
    {
        if (trim($this->aNombre) === '') {
            return;
        }
        $this->filas[] = ['nombre' => trim($this->aNombre), 'caduca' => $this->aCaduca, 'pcs' => '', 'incluir' => true, 'nota' => 'añadido a mano'];
        $this->aNombre = $this->aCaduca = '';
    }

    public function quitar(int $i): void
    {
        unset($this->filas[$i]);
        $this->filas = array_values($this->filas);
    }

    protected function elegidas(): array
    {
        $f = array_values(array_filter($this->filas, fn ($x) => $x['incluir']));
        usort($f, fn ($a, $b) => strcmp($a['caduca'], $b['caduca']));
        return $f;
    }

    protected function texto(): string
    {
        $lineas = array_map(fn ($f) => '• '.($f['caduca'] ? date('d/m/Y', strtotime($f['caduca'])) : 's/f').' — '.$f['nombre']
            .($f['nota'] ? ' ['.$f['nota'].']' : ''), $this->elegidas());
        return trim($this->intro)."\n\n".($lineas ? implode("\n", $lineas) : '(ninguno)')."\n\nAtentamente,\n\nAlexander Arregui\nTel. 638 12 26 14\nSuma Apoyo Empresarial SL\nwww.sumaempresa.com\n{logo}";
    }

    public function pedirEnvio(): void
    {
        $this->confirmar = $this->validarDestino();
    }

    protected function validarDestino(): bool
    {
        $para = $this->lista($this->para);
        if (! $para) {
            $this->salida = '⚠ Falta el destinatario (o no parece un correo).';
            return false;
        }
        return true;
    }

    protected function lista(string $t): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[;,]/', $t)), fn ($a) => filter_var($a, FILTER_VALIDATE_EMAIL)));
    }

    public function enviar(): void
    {
        $this->confirmar = false;
        if (! $this->validarDestino()) {
            return;
        }
        try {
            $mail = strtolower((string) auth()->user()->email);
            $de = str_ends_with($mail, '@sumaempresa.com') ? $mail : config('contabilidad.graph.sender');
            GraphMail::enviar($de, $this->lista($this->para), $this->lista($this->cc), $this->asunto, $this->texto());
            $this->enviado = now()->format('d/m/Y H:i').' a '.implode(', ', $this->lista($this->para));
            $this->registrarEnvio($de);
            $this->salida = '✅ Enviado a '.implode(', ', $this->lista($this->para)).' desde '.$de.'. Márcalo en el Seguimiento mensual.';
        } catch (\Throwable $e) {
            $this->salida = '⚠ No se ha enviado: '.$e->getMessage();
        }
    }

    /** Guarda lo enviado (para revisarlo), marca el mes en el Seguimiento y, si se envió desde un PC, lo repite en la web. */
    protected function registrarEnvio(string $de): void
    {
        $dato = ['periodo' => now()->format('Y-m'), 'enviado_at' => now()->format('Y-m-d H:i:s'), 'origen' => 'app',
            'para' => implode('; ', $this->lista($this->para)), 'cc' => implode('; ', $this->lista($this->cc)) ?: null,
            'asunto' => $this->asunto, 'texto' => $this->texto(), 'filas' => json_encode($this->elegidas(), JSON_UNESCAPED_UNICODE)];
        self::guardarEnvio($dato, auth()->id());
        if (config('contabilidad.ejecucion_local') && ($token = (string) config('contabilidad.certificados_sync_token'))) {
            try {
                $r = Http::withHeaders(['X-Token' => $token])->timeout(30)
                    ->post(str_replace('/escaneo', '/envio', config('contabilidad.certificados_sync_url')), $dato + ['de' => $de]);
                $this->salida .= $r->successful() ? ' 🌐 Guardado también en la web.' : ' ⚠ No se ha guardado en la web (HTTP '.$r->status().').';
            } catch (\Throwable $e) {
                $this->salida .= ' ⚠ No se ha guardado en la web: '.$e->getMessage();
            }
        }
    }

    /** Fila en certificados_envios + check «hecho» del mes en el Seguimiento (proceso «certificados»). */
    public static function guardarEnvio(array $d, ?int $userId): void
    {
        $existe = DB::table('certificados_envios')->where('periodo', $d['periodo'])->where('enviado_at', $d['enviado_at'])->where('para', $d['para'] ?? null)->exists();
        if (! $existe) {
            DB::table('certificados_envios')->insert(['periodo' => $d['periodo'], 'enviado_at' => $d['enviado_at'], 'user_id' => $userId,
                'origen' => $d['origen'] ?? 'app', 'para' => $d['para'] ?? null, 'cc' => $d['cc'] ?? null, 'asunto' => $d['asunto'] ?? null,
                'texto' => $d['texto'] ?? null, 'filas' => $d['filas'] ?? null, 'created_at' => now(), 'updated_at' => now()]);
        }
        if ($proceso = DB::table('seguimiento_procesos')->where('clave', 'certificados')->value('id')) {
            DB::table('seguimiento_marcas')->updateOrInsert(['proceso_id' => $proceso, 'entidad_id' => 0, 'periodo' => $d['periodo']],
                ['estado' => 'ok', 'user_id' => $userId, 'updated_at' => now(), 'created_at' => now()]);
        }
    }

    public function cambiarAnio(int $d): void
    {
        $this->anio += $d;
        $this->verEnvio = null;
    }

    public function ver(int $id): void
    {
        $this->verEnvio = $this->verEnvio === $id ? null : $id;
    }

    public function render()
    {
        return view('livewire.contabilidad.certificados', [
            'vistaPrevia' => $this->texto(),
            'envios' => DB::table('certificados_envios')->where('periodo', 'like', $this->anio.'-%')->orderBy('enviado_at')->get()->groupBy('periodo'),
            'envioAbierto' => $this->verEnvio ? DB::table('certificados_envios')->find($this->verEnvio) : null,
            'graphOk' => GraphMail::configurado(),
            'enLocal' => (bool) config('contabilidad.ejecucion_local'),
        ]);
    }
}
