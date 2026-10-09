<?php

namespace App\Http\Livewire;

use App\Support\ColaTareas;
use App\Support\Impuestos as Imp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Livewire\Component;

/**
 * Libro de IVA (303) maquetado (9-oct-2026). Pestaña Impuestos → «📒 Libro de IVA». Un PC trabajador, con OneDrive, hace el trabajo
 * (tarea `pc.libro_iva`, motor Contabilidad/Impuestos/M303/libro_iva.py): busca el Excel original del periodo en la carpeta IVA del cliente,
 * monta el libro (EXPEDIDAS/RECIBIDAS con subtotales por tipo de IVA y resultado) y lo deja en esa misma carpeta; aquí se ve el resultado
 * y se descargan el Excel y los avisos. La carpeta de cada empresa se elige con el selector de Windows (`pc.elegir_carpeta`) y se recuerda.
 */
class LibroIva extends Component
{
    public string $entidadId = '';
    public string $ejercicio = '';
    public string $periodo = '3T';
    public string $carpeta = '';

    /** Qué se espera de un PC: '' | 'ventana' | 'listar' | 'generar'. */
    public string $espera = '';
    public string $ventanaToken = '';
    public ?int $tareaId = null;
    public string $mensaje = '';
    public string $error = '';
    public ?array $listado = null;
    public ?array $libro = null;

    public function mount(): void
    {
        $g = (array) session('libro_iva', []);
        $emp = $this->empresas();
        $this->entidadId = isset($emp[$g['entidad'] ?? '']) ? (string) $g['entidad'] : (string) ($emp ? array_key_first($emp) : '');
        $m = (int) date('n');
        $t = intdiv($m - 1, 3);   // trimestre natural ya terminado
        $this->ejercicio = (string) ($g['ejercicio'] ?? ($t === 0 ? date('Y') - 1 : date('Y')));
        $this->periodo = (string) ($g['periodo'] ?? ($t === 0 ? '4T' : $t.'T'));
        $this->alCambiarEmpresa();
    }

    public function updated($prop): void
    {
        if ($prop === 'entidadId') {
            $this->alCambiarEmpresa();
        }
        if (in_array($prop, ['entidadId', 'ejercicio', 'periodo'], true)) {
            session(['libro_iva' => ['entidad' => $this->entidadId, 'ejercicio' => $this->ejercicio, 'periodo' => $this->periodo]]);
            $this->libro = null;
            $this->mensaje = $this->error = '';
        }
    }

    protected function alCambiarEmpresa(): void
    {
        $this->carpeta = (string) (DB::table('libro_iva_carpetas')->where('entidad_id', (int) $this->entidadId)->value('carpeta') ?? '');
        $this->listado = null;
        $this->libro = null;
        $this->espera = $this->ventanaToken = '';
        $this->tareaId = null;
        $this->recuperarUltimo();
    }

    /** Empresas con 303 visibles para el usuario (las mismas reglas que la pestaña Impuestos). */
    public function empresas(): array
    {
        $q = DB::table('entidad_impuestos as ei')->join('entidades as e', 'e.id', '=', 'ei.entidad_id')
            ->join('impuesto_modelos as m', 'm.id', '=', 'ei.modelo_id')->where('m.codigo', '303')->where('e.estado', 1)
            ->select('e.id', DB::raw('COALESCE(NULLIF(e.alias, ""), e.entidad) as nombre'))->distinct()->orderBy('nombre');
        Imp::soloVisibles($q, null, Imp::esGestor());

        return $q->pluck('nombre', 'e.id')->all();
    }

    protected function nombreEmpresa(): string
    {
        return (string) ($this->empresas()[$this->entidadId] ?? '');
    }

    /** «3T» → 2026-3T · «07» → 2026-07 */
    protected function periodoMotor(): string
    {
        return $this->ejercicio.'-'.$this->periodo;
    }

    public function periodoValido(): bool
    {
        return (bool) preg_match('/^\d{4}$/', $this->ejercicio) && (bool) preg_match('/^([1-4]T|0[1-9]|1[0-2])$/', $this->periodo);
    }

    // ------------------------------------------------------------------ carpeta (selector de Windows en un PC)

    public function elegirCarpeta(): void
    {
        $this->error = $this->mensaje = '';
        if (! $this->hayPc()) {
            return;
        }
        $this->ventanaToken = bin2hex(random_bytes(6));
        $this->espera = 'ventana';
        ColaTareas::crear('pc.elegir_carpeta', ['token' => $this->ventanaToken], ColaTareas::pcElegido() ?: null, auth()->id());
        $this->mensaje = 'Se ha abierto el selector de carpetas de Windows en el PC trabajador: elige la carpeta IVA de la empresa.';
    }

    public function cancelarEspera(): void
    {
        $this->espera = $this->ventanaToken = '';
        $this->mensaje = '';
    }

    // ------------------------------------------------------------------ tareas

    protected function hayPc(): bool
    {
        $vivos = DB::table('trabajadores')->where('activo', true)->where('ultimo_latido', '>=', now()->subSeconds(ColaTareas::LATIDO_MAX))->count();
        if ($vivos === 0) {
            $this->error = 'No hay ningún PC trabajador conectado ahora mismo (mira la barra de arriba).';
        }

        return $vivos > 0;
    }

    public function comprobar(): void
    {
        $this->lanzar('listar');
    }

    public function generar(): void
    {
        if (! $this->periodoValido()) {
            $this->error = 'Periodo no válido.';

            return;
        }
        $this->lanzar('generar');
    }

    protected function lanzar(string $accion): void
    {
        $this->error = $this->mensaje = '';
        if ($this->carpeta === '') {
            $this->error = 'Elige primero la carpeta donde están los Excel de IVA de esta empresa.';

            return;
        }
        abort_unless(\App\Support\ColaTareas::rutaRelativaSegura($this->carpeta), 422);
        if (! $this->hayPc()) {
            return;
        }
        if ($accion === 'generar') {
            $this->libro = null;
        }
        $this->tareaId = ColaTareas::crear('pc.libro_iva', [
            'accion' => $accion, 'carpeta' => $this->carpeta, 'periodo' => $this->periodoMotor(),
            'cliente' => $this->nombreEmpresa(), 'entidad_id' => (int) $this->entidadId,
        ], ColaTareas::pcElegido() ?: null, auth()->id());
        $this->espera = $accion;
        $this->mensaje = $accion === 'generar' ? 'Pedido a un PC: monta el libro en la carpeta de OneDrive…' : 'Pedido a un PC: mirando la carpeta…';
    }

    /** wire:poll mientras se espera a un PC. */
    public function revisar(): void
    {
        if ($this->espera === 'ventana') {
            $e = ColaTareas::estado('onedrive.carpeta_elegida');
            if (! is_array($e) || ($e['token'] ?? '') !== $this->ventanaToken) {
                return;
            }
            $this->espera = $this->ventanaToken = '';
            $this->mensaje = '';
            if (! empty($e['cancelada'])) {
                return;
            }
            if (! empty($e['error']) || empty($e['ruta'])) {
                $this->error = (string) ($e['error'] ?: 'No he podido leer la carpeta elegida.');

                return;
            }
            $ruta = trim((string) $e['ruta'], '/');
            if (! ColaTareas::rutaRelativaSegura($ruta)) {
                $this->error = 'Carpeta no válida.';

                return;
            }
            $this->carpeta = $ruta;
            DB::table('libro_iva_carpetas')->updateOrInsert(['entidad_id' => (int) $this->entidadId], ['carpeta' => $ruta, 'user_id' => auth()->id(), 'updated_at' => now(), 'created_at' => now()]);
            $this->listado = null;
            $this->comprobar();

            return;
        }
        if (! in_array($this->espera, ['listar', 'generar'], true) || ! $this->tareaId) {
            return;
        }
        $t = DB::table('tareas')->find($this->tareaId);
        if (! $t || in_array($t->estado, ['pendiente', 'en_curso'], true)) {
            return;
        }
        $accion = $this->espera;
        $this->espera = '';
        $r = json_decode($t->resultado ?? 'null', true) ?: [];
        if ($t->estado !== 'ok') {
            $this->error = 'No se ha podido: '.(string) ($r['error'] ?? trim(mb_substr((string) $t->log, -400)) ?: 'error en el PC');

            return;
        }
        $this->mensaje = '';
        if ($accion === 'listar') {
            $this->listado = $r['listado'] ?? null;
        } else {
            $this->libro = $r['libro'] ?? null;
            if ($this->libro) {
                $this->libro['tarea'] = $t->id;
                $this->libro['pc'] = $r['pc'] ?? '';
            }
        }
    }

    protected function recuperarUltimo(): void
    {
        $e = ColaTareas::estado('impuestos.libro_iva.'.(int) $this->entidadId);
        $this->ultimo = is_array($e) ? $e : null;
    }

    public ?array $ultimo = null;

    public function enlace(string $fichero): string
    {
        return URL::temporarySignedRoute('contabilidad.tarea-fichero', now()->addHours(2), ['id' => $this->libro['tarea'], 'nombre' => $fichero]);
    }

    /** ¿Hay original del periodo elegido en la carpeta? Según el último listado. */
    public function originalDelPeriodo(): ?array
    {
        $per = $this->periodoMotor();
        foreach ($this->listado['originales'] ?? [] as $o) {
            if ($o['periodo'] === $per) {
                return $o;
            }
        }

        return null;
    }

    public function render()
    {
        return view('livewire.libro-iva', ['empresas' => $this->empresas()]);
    }
}
