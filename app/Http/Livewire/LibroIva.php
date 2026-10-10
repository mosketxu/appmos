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
    /** Admin/Suma: ver las empresas de todos (por defecto solo las suyas, como en Impuestos). */
    public bool $verTodos = false;

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
        $this->verTodos = $this->puedeTodos() && (bool) ($g['verTodos'] ?? false);
        $emp = $this->empresas();
        $this->entidadId = isset($emp[$g['entidad'] ?? '']) ? (string) $g['entidad'] : (string) ($emp ? array_key_first($emp) : '');
        $m = (int) date('n');
        $t = intdiv($m - 1, 3);   // trimestre natural ya terminado
        $this->ejercicio = (string) ($g['ejercicio'] ?? ($t === 0 ? date('Y') - 1 : date('Y')));
        $this->periodo = (string) ($g['periodo'] ?? ($t === 0 ? '4T' : $t.'T'));
        $this->alCambiarEmpresa();
        if (! $this->carpetasIva()) {   // primera vez: un PC lee las carpetas IVA de OneDrive para el desplegable
            $this->actualizarCarpetas();
        }
    }

    public function updated($prop): void
    {
        if ($prop === 'verTodos') {
            $this->verTodos = $this->puedeTodos() && $this->verTodos;
            if (! isset($this->empresas()[$this->entidadId])) {
                $this->entidadId = (string) (array_key_first($this->empresas()) ?? '');
                $prop = 'entidadId';
            }
        }
        if ($prop === 'entidadId') {
            $this->alCambiarEmpresa();
        }
        if (in_array($prop, ['entidadId', 'ejercicio', 'periodo', 'verTodos'], true)) {
            session(['libro_iva' => ['entidad' => $this->entidadId, 'ejercicio' => $this->ejercicio, 'periodo' => $this->periodo, 'verTodos' => $this->verTodos]]);
            $this->libro = null;
            $this->mensaje = $this->error = '';
        }
    }

    /** Periodicidad del 303 de la empresa en Impuestos: 'M' mensual, 'T' trimestral o '' si no consta. */
    public function periodicidad(): string
    {
        $p = (string) DB::table('entidad_impuestos as ei')->join('impuesto_modelos as m', 'm.id', '=', 'ei.modelo_id')
            ->where('ei.entidad_id', (int) $this->entidadId)->where('m.codigo', '303')->whereNull('ei.baja_ejercicio')->value('ei.periodicidad');

        return in_array($p, ['M', 'T'], true) ? $p : '';
    }

    /** Una empresa mensual trabaja con meses (09 …) y una trimestral con trimestres (3T): si el periodo elegido no es de su tipo, pasa al último ya terminado. */
    protected function ajustarPeriodo(): void
    {
        $per = $this->periodicidad();
        $esTrimestre = (bool) preg_match('/^[1-4]T$/', $this->periodo);
        $m = (int) date('n');
        if ($per === 'M' && $esTrimestre) {
            $this->periodo = str_pad((string) ($m === 1 ? 12 : $m - 1), 2, '0', STR_PAD_LEFT);
            if ($m === 1 && (int) $this->ejercicio === (int) date('Y')) {
                $this->ejercicio = (string) ((int) date('Y') - 1);
            }
        } elseif ($per === 'T' && ! $esTrimestre) {
            $t = intdiv($m - 1, 3);
            $this->periodo = $t === 0 ? '4T' : $t.'T';
            if ($t === 0 && (int) $this->ejercicio === (int) date('Y')) {
                $this->ejercicio = (string) ((int) date('Y') - 1);
            }
        }
    }

    protected function alCambiarEmpresa(): void
    {
        $this->ajustarPeriodo();
        $this->carpeta = (string) (DB::table('libro_iva_carpetas')->where('entidad_id', (int) $this->entidadId)->value('carpeta') ?? '');
        $this->listado = null;
        $this->libro = null;
        $this->espera = $this->ventanaToken = '';
        $this->tareaId = null;
        $this->recuperarUltimo();
    }

    public function puedeTodos(): bool
    {
        return Imp::esGestor();
    }

    /** Empresas con 303 visibles para el usuario (las mismas reglas que la pestaña Impuestos). */
    public function empresas(): array
    {
        $q = DB::table('entidad_impuestos as ei')->join('entidades as e', 'e.id', '=', 'ei.entidad_id')
            ->join('impuesto_modelos as m', 'm.id', '=', 'ei.modelo_id')->where('m.codigo', '303')->where('e.estado', 1)
            ->select('e.id', DB::raw('COALESCE(NULLIF(e.alias, ""), e.entidad) as nombre'))->distinct()->orderBy('nombre');
        Imp::soloVisibles($q, null, $this->verTodos);

        return $q->pluck('nombre', 'e.id')->all();
    }

    protected function nombreEmpresa(): string
    {
        return (string) ($this->empresas()[$this->entidadId] ?? '');
    }

    /** ¿La empresa presenta el modelo 216 (no residentes)? Entonces el libro lleva la tabla por proveedor junto al bloque ISP. */
    public function presentaM216(): bool
    {
        return DB::table('entidad_impuestos as ei')->join('impuesto_modelos as m', 'm.id', '=', 'ei.modelo_id')
            ->where('ei.entidad_id', (int) $this->entidadId)->where('m.codigo', '216')->whereNull('ei.baja_ejercicio')->exists();
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

    // ------------------------------------------------------------------ carpeta (desplegable con buscador, con las carpetas IVA que lee un PC)

    public string $carpetaElegida = '';

    /**
     * Raíces de OneDrive que puede ver el usuario en el explorador (10-oct-2026): `_Clientes` todos; `_RUR_Marta_Alex` solo Alex y Marta Ruiz
     * (config contabilidad.libro_iva_acceso_total); `_Suma` además los roles Suma y Admin.
     */
    public static function raicesPermitidas(?\App\Models\User $u = null): array
    {
        $u ??= auth()->user();
        $raices = ['_Clientes'];
        if ($u && in_array(strtolower((string) $u->email), array_map('strtolower', (array) config('contabilidad.libro_iva_acceso_total', [])), true)) {
            return ['_Clientes', '_RUR_Marta_Alex', '_Suma'];
        }
        if ($u && $u->hasAnyRole(['Admin', 'Suma'])) {
            $raices[] = '_Suma';
        }

        return $raices;
    }

    public static function rutaPermitida(string $ruta, ?\App\Models\User $u = null): bool
    {
        foreach (self::raicesPermitidas($u) as $r) {
            if ($ruta === $r || str_starts_with($ruta, $r.'/')) {
                return true;
            }
        }

        return false;
    }

    /** Árbol de carpetas de OneDrive que subió un PC (tarea pc.arbol_carpetas modo iva), filtrado por lo que puede ver el usuario: ['dirs' => [...], 'marcas' => [...]]. */
    public function arbolCarpetas(): array
    {
        try {
            $a = ColaTareas::estado('onedrive.arbol_iva');
        } catch (\Throwable $e) {
            $a = null;
        }
        $ok = fn ($d) => self::rutaPermitida((string) $d);

        return ['dirs' => array_values(array_filter((array) ($a['dirs'] ?? []), $ok)), 'marcas' => array_values(array_filter((array) ($a['marcas'] ?? []), $ok))];
    }

    public function carpetasIva(): array
    {
        return array_flip($this->arbolCarpetas()['dirs']);
    }

    public function actualizarCarpetas(): void
    {
        $this->error = '';
        if (! $this->hayPc()) {
            return;
        }
        if (! DB::table('tareas')->where('proceso', 'pc.arbol_carpetas')->whereIn('estado', ['pendiente', 'en_curso'])->exists()) {
            ColaTareas::crear('pc.arbol_carpetas', ['modo' => 'iva'], ColaTareas::pcElegido() ?: null, auth()->id());
        }
        $this->espera = 'carpetas';
        $this->mensaje = 'Un PC está leyendo las carpetas IVA de OneDrive (unos segundos)…';
    }

    public function updatedCarpetaElegida(string $ruta): void
    {
        if ($ruta === '' || ! isset($this->carpetasIva()[$ruta]) || ! ColaTareas::rutaRelativaSegura($ruta)) {
            return;
        }
        $this->carpeta = $ruta;
        $this->carpetaElegida = '';
        DB::table('libro_iva_carpetas')->updateOrInsert(['entidad_id' => (int) $this->entidadId], ['carpeta' => $ruta, 'user_id' => auth()->id(), 'updated_at' => now(), 'created_at' => now()]);
        $this->listado = null;
        $this->comprobar();
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
            'cliente' => $this->nombreEmpresa(), 'entidad_id' => (int) $this->entidadId, 'm216' => $this->presentaM216(),
        ], ColaTareas::pcElegido() ?: null, auth()->id());
        $this->espera = $accion;
        $this->mensaje = $accion === 'generar' ? 'Pedido a un PC: monta el libro en la carpeta de OneDrive…' : 'Pedido a un PC: mirando la carpeta…';
    }

    /** wire:poll mientras se espera a un PC. */
    public function revisar(): void
    {
        if ($this->espera === 'carpetas') {
            if (! DB::table('tareas')->where('proceso', 'pc.arbol_carpetas')->whereIn('estado', ['pendiente', 'en_curso'])->exists()) {
                $this->espera = $this->mensaje = '';
                if (! $this->carpetasIva()) {
                    $this->error = 'El PC no ha encontrado carpetas IVA en OneDrive.';
                }
            }

            return;
        }
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
