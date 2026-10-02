<?php

namespace App\Http\Livewire\Contabilidad;

use App\Support\CertificadosLista;
use App\Support\GraphMail;
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
    public string $intro = "Hola Marta,\n\nEstos son los certificados digitales que caducan en los próximos meses. Ya está comprobado que no hay una versión más reciente instalada en AlexMiniPC o PortalExomen.";
    public bool $confirmar = false;
    public string $enviado = '';

    // Fila nueva a mano
    public string $aNombre = '';
    public string $aCaduca = '';

    public function mount(): void
    {
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
        return trim($this->intro)."\n\n".($lineas ? implode("\n", $lineas) : '(ninguno)')."\n\nUn saludo,\n\nAlexander Arregui\nTel. 638 12 26 14\nSuma Apoyo Empresarial SL\nwww.sumaempresa.com\n{logo}";
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
            $this->salida = '✅ Enviado a '.implode(', ', $this->lista($this->para)).' desde '.$de.'. Márcalo en el Seguimiento mensual.';
        } catch (\Throwable $e) {
            $this->salida = '⚠ No se ha enviado: '.$e->getMessage();
        }
    }

    public function render()
    {
        return view('livewire.contabilidad.certificados', [
            'vistaPrevia' => $this->texto(),
            'graphOk' => GraphMail::configurado(),
            'enLocal' => (bool) config('contabilidad.ejecucion_local'),
        ]);
    }
}
