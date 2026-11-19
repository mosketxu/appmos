<?php

namespace App\Http\Livewire;

use App\Models\ConfidencialidadDoc;
use App\Support\Confidencialidad as Conf;
use App\Support\GraphMail;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * «✉ Correo» (19-nov-2026): bandeja de entrada a la izquierda, el correo en el centro (se ve en claro SOLO aquí, nunca sale hacia Anthropic) y a la derecha lo que se quiere hacer con él.
 * Para trabajar con Claude el correo (cuerpo + adjuntos) pasa por el mismo anonimizador que la pantalla Confidencialidad (se reutiliza todo): primera pasada automática,
 * «dar por bueno» (borra el original) y chat con Claude, que solo ve lo anonimizado. Lo puede usar cualquier usuario con el permiso correo.ver (19-nov-2026) y cada uno lee SOLO su buzón.
 */
class CorreoClaude extends Component
{
    use WithFileUploads;

    public string $busqueda = '';
    public int $max = 40;
    public ?string $sel = null;
    /** Carpeta que se ve: «inbox» o el id de Graph. */
    public string $carpeta = 'inbox';
    /** Carpetas con las subcarpetas desplegadas (ids). */
    public array $abiertas = [];
    public bool $mostrarMover = false;
    public string $filtroCarpeta = '';
    public string $perfil = 'estricto';
    public string $nombres = '';
    public string $empresas = '';
    public string $instruccion = '';
    public string $aviso = '';
    /** Voluntario: ver las imágenes del correo (las incrustadas y las remotas). Por defecto no, para no avisar al remitente de que se ha abierto. */
    public bool $verImagenes = false;
    /** Borrador propuesto por Claude (editable antes de crearlo en Outlook). */
    public string $bModo = 'respuesta';     // respuesta | nuevo
    public bool $bTodos = false;
    public string $bPara = '';
    public string $bAsunto = '';
    public string $bTexto = '';
    public string $bHash = '';
    public string $bHecho = '';
    /** Adjuntos voluntarios del borrador: ficheros subidos, adjuntos del correo original (ids de Graph) y el id del último borrador creado a mano. */
    public array $subidasAdj = [];
    public array $adjOriginales = [];
    public string $bBorradorId = '';
    /** Vista previa de un adjunto de imagen del correo abierto (id de Graph). */
    public ?string $verAdj = null;

    public function hydrate(): void
    {
        abort_unless(auth()->user()?->can('correo.ver'), 403);
    }

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('correo.ver'), 403);
    }

    /**
     * Caché de lo que se lee del buzón. Contiene datos personales (asuntos, cuerpos, adjuntos): solo se guarda dentro del almacén cifrado de Confidencialidad;
     * si está cerrado, no se guarda en ningún disco (caché en memoria de la petición).
     */
    protected function cache(): \Illuminate\Contracts\Cache\Repository
    {
        if (! Conf::almacenAbierto()) {
            return Cache::store('array');
        }
        $dir = storage_path('app/confidencialidad/_cache_correo');
        if (! is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }

        return Cache::store('correo');
    }

    /** El buzón es SIEMPRE el del usuario que está conectado (nunca uno elegido en pantalla): cada uno ve solo el suyo. */
    protected function buzon(): string
    {
        $e = (string) auth()->user()?->email;
        abort_if($e === '', 403, 'Tu usuario no tiene correo.');

        return mb_strtolower($e);
    }

    protected function lineas(string $t): array
    {
        return array_values(array_unique(array_filter(array_map('trim', preg_split('/\R/u', $t) ?: []), fn ($x) => mb_strlen($x) >= 3)));
    }

    public function irACarpeta(string $id): void
    {
        $this->carpeta = $id;
        $this->sel = null;
        $this->max = 40;
        $this->busqueda = '';
        $this->mostrarMover = false;
        $this->aviso = '';
    }

    public function alternar(string $id): void
    {
        $this->abiertas = in_array($id, $this->abiertas, true) ? array_values(array_diff($this->abiertas, [$id])) : [...$this->abiertas, $id];
    }

    public function abrirMover(): void
    {
        $this->mostrarMover = ! $this->mostrarMover;
        $this->filtroCarpeta = '';
    }

    /** Mueve el correo abierto a otra carpeta. */
    public function mover(string $destino): void
    {
        abort_unless($this->sel && $destino !== '', 422);
        $this->moverId($destino, $this->sel);
    }

    /** «Eliminar» como en Outlook: el correo va a Elementos eliminados (se puede recuperar de allí; no se borra para siempre desde esta pantalla). */
    public function eliminar(): void
    {
        abort_unless($this->sel, 422);
        $destino = $this->arbol()['eliminados'] ?? null;
        if (! $destino) {
            $this->aviso = 'No se ha encontrado la carpeta de elementos eliminados.';

            return;
        }
        $this->moverId($destino, $this->sel);
        $this->sel = null;                 // eliminado: se cierra (el que se mueve a una carpeta sigue abierto)
        if ($this->aviso === 'Correo movido.') {
            $this->aviso = 'Correo enviado a Elementos eliminados.';
        }
    }

    /** Mueve un correo concreto (el que se arrastra a una carpeta). Graph le da un id nuevo: se cierra el abierto y se refresca todo. */
    public function moverId(string $destino, string $id): void
    {
        abort_unless($id !== '' && $destino !== '', 422);
        if ($destino === $this->carpeta) {
            return;                                   // soltarlo en la carpeta donde ya está
        }
        try {
            try {
                GraphMail::marcarLeido($this->buzon(), $id);      // al archivarlo queda como leído (si falla, no impide moverlo)
            } catch (\Throwable $e) {
            }
            $nuevo = GraphMail::mover($this->buzon(), $id, $destino);
        } catch (\Throwable $e) {
            $this->aviso = 'No se ha podido mover el correo.';

            return;
        }
        if ($nuevo !== '') {      // al moverse, Graph le da otro id al correo: las conversaciones con Claude de este correo pasan a su nueva ubicación (no se pierden)
            ConfidencialidadDoc::where('user_id', auth()->id())->where('correo_id', $id)->update(['correo_id' => $nuevo]);
        }
        if ($id === $this->sel) {
            $this->sel = $nuevo !== '' ? $nuevo : null;      // sigue abierto el mismo correo, ya en su carpeta nueva
        }
        $this->mostrarMover = false;
        Cache::forever('correo_claude_v', $this->v() + 1);     // listas y recuentos de carpetas: se vuelven a pedir
        $this->aviso = 'Correo movido.';
    }

    /** Carpetas de primer nivel (las de siempre primero) con las subcarpetas desplegadas, en una lista plana con su nivel. */
    protected function arbol(): array
    {
        $b = $this->buzon();
        $conocidas = $this->cache()->remember('correo_claude_conocidas_'.md5($b), 3600, function () use ($b) {
            $o = [];
            foreach (['inbox', 'drafts', 'sentitems', 'deleteditems', 'junkemail', 'archive'] as $i => $n) {
                if ($id = GraphMail::carpetaConocida($b, $n)) {
                    $o[$id] = $i;
                }
            }

            return $o;
        });
        $raiz = $this->cache()->remember('correo_claude_raiz_'.md5($this->v().$b), 120, fn () => GraphMail::carpetasHijas($b));
        usort($raiz, fn ($x, $y) => [$conocidas[$x['id']] ?? 99, mb_strtolower($x['nombre'])] <=> [$conocidas[$y['id']] ?? 99, mb_strtolower($y['nombre'])]);
        $plana = [];
        $anadir = function (array $lista, int $nivel) use (&$anadir, &$plana, $b) {
            foreach ($lista as $c) {
                $abierta = in_array($c['id'], $this->abiertas, true);
                $plana[] = $c + ['nivel' => $nivel, 'abierta' => $abierta];
                if ($abierta && $c['hijos'] > 0) {
                    $h = $this->cache()->remember('correo_claude_hijas_'.md5($this->v().$b.$c['id']), 120, fn () => GraphMail::carpetasHijas($b, $c['id']));
                    usort($h, fn ($x, $y) => strnatcasecmp($x['nombre'], $y['nombre']));
                    $anadir($h, $nivel + 1);
                }
            }
        };
        $anadir($raiz, 0);

        return ['plana' => $plana, 'inbox' => array_search(0, $conocidas, true), 'eliminados' => array_search(3, $conocidas, true)];
    }

    /** Todas las carpetas con su ruta (para el selector de «Mover a…»), recorridas por niveles; tope de 300 por si el buzón es enorme. */
    protected function todasLasCarpetas(): array
    {
        $b = $this->buzon();

        return $this->cache()->remember('correo_claude_todas_'.md5($this->v().$b), 600, function () use ($b) {
            $out = [];
            $cola = array_map(fn ($c) => $c + ['ruta' => $c['nombre']], GraphMail::carpetasHijas($b));
            while ($cola && count($out) < 300) {
                $c = array_shift($cola);
                $out[] = ['id' => $c['id'], 'ruta' => $c['ruta']];
                if ($c['hijos'] > 0) {
                    foreach (GraphMail::carpetasHijas($b, $c['id']) as $h) {
                        $cola[] = $h + ['ruta' => $c['ruta'].' / '.$h['nombre']];
                    }
                }
            }
            usort($out, fn ($x, $y) => strnatcasecmp($x['ruta'], $y['ruta']));

            return $out;
        });
    }

    /** Direcciones válidas de un texto (separadas por coma, punto y coma o espacios). */
    protected function direcciones(string $t): array
    {
        return array_values(array_filter(preg_split('/[\s,;]+/', $t) ?: [], fn ($x) => filter_var($x, FILTER_VALIDATE_EMAIL)));
    }

    protected function firma(): string
    {
        return Conf::firmaDe(auth()->user());
    }

    /** Usa la última respuesta de Claude entera como borrador (si no la dio entre ---BORRADOR--- y ---FIN---). */
    public function usarRespuesta(): void
    {
        $d = $this->doc();
        abort_unless($d, 422);
        foreach (array_reverse(Conf::conversacion($d)) as $r) {
            if ($r['quien'] === 'claude') {
                $this->bTexto = trim($r['texto']);
                $this->bHash = md5('manual'.$this->bTexto);
                $this->bHecho = '';

                return;
            }
        }
    }

    public function verAdjunto(?string $id): void
    {
        $this->verAdj = ($id === null || $id === $this->verAdj) ? null : $id;
    }

    /** Adjunta al borrador $borradorId lo que se haya elegido (adjuntos del correo original y ficheros subidos). Devuelve cuántos. */
    protected function aplicarAdjuntos(string $borradorId): int
    {
        $this->validate(['subidasAdj.*' => 'file|max:12288'], ['subidasAdj.*.max' => 'Cada fichero puede pesar hasta 12 MB.', 'subidasAdj.*.file' => 'Fichero no válido.']);
        $n = 0;
        foreach ($this->adjOriginales as $aid) {
            $a = GraphMail::adjuntoContenido($this->buzon(), $this->sel, $aid);
            $bytes = base64_decode($a['bytes'], true);
            if ($bytes === false || $bytes === '') {
                continue;
            }
            $tmp = tempnam(sys_get_temp_dir(), 'adj_');
            file_put_contents($tmp, $bytes);
            try {
                GraphMail::adjuntar($this->buzon(), $borradorId, $a['nombre'], $tmp);
                $n++;
            } finally {
                @unlink($tmp);
            }
        }
        foreach ($this->subidasAdj as $f) {
            GraphMail::adjuntar($this->buzon(), $borradorId, $f->getClientOriginalName(), $f->getRealPath());
            $n++;
        }
        $this->adjOriginales = [];
        $this->subidasAdj = [];

        return $n;
    }

    /** Añade adjuntos a un borrador ya creado (el de la última petición, o el creado a mano). */
    public function adjuntarAlBorrador(): void
    {
        $d = $this->doc();
        $id = (string) ($d ? (Conf::leerMeta($d)['borrador_auto']['id'] ?? '') : '') ?: $this->bBorradorId;
        if ($id === '') {
            $this->aviso = 'Todavía no hay un borrador creado al que adjuntar.';

            return;
        }
        try {
            $n = $this->aplicarAdjuntos($id);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->aviso = 'No se han podido adjuntar los ficheros al borrador.';

            return;
        }
        $this->aviso = $n ? "Se han añadido {$n} adjunto(s) al borrador. Si ya lo tenías abierto en Outlook, ciérralo y vuelve a abrirlo (F9)." : 'No has elegido ningún adjunto.';
    }

    /** Crea el borrador en el Outlook de quien está conectado (carpeta Borradores; no se envía nada). */
    public function crearBorrador(): void
    {
        abort_unless($this->sel, 422);
        $this->validate(['bTexto' => 'required|string|min:3|max:20000', 'bModo' => 'required|in:respuesta,nuevo', 'bAsunto' => 'nullable|string|max:250', 'bPara' => 'nullable|string|max:500'],
            ['bTexto.required' => 'El borrador está vacío.', 'bTexto.min' => 'El borrador está vacío.']);
        $html = GraphMail::html(rtrim($this->bTexto)."\n\n".$this->firma()).GraphMail::pieRgpd();      // texto + firma + logo + pie legal
        try {
            $id = $this->bModo === 'respuesta'
                ? GraphMail::crearBorradorRespuesta($this->buzon(), $this->sel, $html, $this->bTodos)
                : GraphMail::crearBorradorHtml($this->buzon(), $this->direcciones($this->bPara), [], trim($this->bAsunto) ?: '(sin asunto)', $html);
            $this->bBorradorId = $id;
            $n = $this->aplicarAdjuntos($id);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->aviso = 'No se ha podido crear el borrador en Outlook.';

            return;
        }
        $this->bHecho = 'Borrador creado en tu carpeta Borradores de Outlook (no se envía solo)'.($n ? " con {$n} adjunto(s)" : '').'. Revísalo y envíalo desde Outlook.';
    }

    public function seleccionar(string $id): void
    {
        $this->sel = $id;
        $this->verImagenes = false;
        $this->bTexto = $this->bHash = $this->bHecho = $this->bPara = $this->bBorradorId = '';
        $this->subidasAdj = $this->adjOriginales = [];
        $this->verAdj = null;
        $this->bModo = 'respuesta';
        $this->bTodos = false;
        $this->instruccion = '';
        $this->aviso = '';
    }

    public function masCorreos(): void
    {
        $this->max = min($this->max + 40, 200);
    }

    public function refrescar(): void
    {
        $this->cache()->forget($this->claveLista());
        $this->aviso = '';
    }

    /** Versión de la caché de listas y carpetas: se sube al mover un correo (así no se vacía la caché de toda la aplicación). */
    protected function v(): int
    {
        return (int) Cache::get('correo_claude_v', 1);
    }

    protected function claveLista(): string
    {
        return 'correo_claude_lista_'.md5($this->v().'|'.$this->buzon().'|'.$this->carpeta.'|'.$this->max.'|'.mb_strtolower(trim($this->busqueda)));
    }

    /** El documento anonimizado de este correo (el último de quien está conectado), si ya se preparó. */
    protected function doc(): ?ConfidencialidadDoc
    {
        return $this->sel ? ConfidencialidadDoc::where('user_id', auth()->id())->where('correo_id', $this->sel)->latest('id')->first() : null;
    }

    protected function docOFalla(): ConfidencialidadDoc
    {
        $d = $this->doc();
        abort_unless($d, 422);

        return $d;
    }

    /** Trae el correo (cuerpo + adjuntos) y lo anonimiza en segundo plano. */
    public function preparar(bool $enviar = false): void
    {
        $this->aviso = '';
        abort_unless($this->sel, 422);
        $this->validate(['perfil' => 'required|in:estricto,contable,mercantil']);
        if ($enviar) {
            $this->validate(['instruccion' => 'required|string|min:3|max:4000'], ['instruccion.required' => 'Escribe qué quieres que haga Claude.', 'instruccion.min' => 'Escribe qué quieres que haga Claude.']);
        }
        if (! Conf::almacenAbierto()) {
            $this->aviso = Conf::MENSAJE_CERRADO;

            return;
        }
        try {
            $m = $this->mensaje();
            $archivos = Conf::archivosDeCorreo($this->buzon(), $this->sel);
        } catch (\Throwable $e) {
            $this->aviso = 'No se ha podido traer el correo del buzón.';

            return;
        }
        $d = Conf::crear(auth()->user(), array_slice($archivos, 0, 25), $this->lineas($this->nombres), $this->lineas($this->empresas), $this->perfil, mb_substr('Correo: '.($m['asunto'] ?? ''), 0, 120), $enviar ? $this->instruccion : null);
        $d->update(['correo_id' => $this->sel]);
        $this->nombres = $this->empresas = '';
        if ($enviar) {
            $this->instruccion = '';
        }
        $this->aviso = $d->estado === 'error' ? '' : ($enviar ? 'Anonimizando en segundo plano; en cuanto termine se envía a Claude solo (no hace falta que esperes aquí).'
            : 'Anonimizando en segundo plano (los adjuntos escaneados tardan). Cuando termine podrás revisarlo.');
    }

    /** Voluntario: trabajar el correo con Claude SIN anonimizar (la pantalla ya ha pedido confirmación). */
    public function sinAnonimizar(): void
    {
        $this->aviso = '';
        abort_unless($this->sel, 422);
        try {
            $m = $this->mensaje();
            $archivos = Conf::archivosDeCorreo($this->buzon(), $this->sel);
        } catch (\Throwable $e) {
            $this->aviso = 'No se ha podido traer el correo del buzón.';

            return;
        }
        [$d, $omitidos] = Conf::crearSinAnonimizar(auth()->user(), $archivos, mb_substr('Correo (sin anonimizar): '.($m['asunto'] ?? ''), 0, 120));
        $d->update(['correo_id' => $this->sel]);
        $extra = $omitidos ? 'No se incluyen estos adjuntos (solo PDF, imágenes y texto): '.implode(', ', $omitidos).'. ' : '';
        if (mb_strlen(trim($this->instruccion)) >= 3) {          // con el texto ya escrito se envía en el mismo paso
            Conf::pedirAClaude($d, auth()->user(), $this->instruccion);
            $this->instruccion = '';
            $this->aviso = 'Enviado a Claude tal cual (sin anonimizar). '.$extra.'La respuesta sale aquí en cuanto la tenga (suele ser menos de un minuto).';

            return;
        }
        $this->aviso = 'Listo: el correo irá a Claude tal cual. '.$extra.'Escribe qué quieres que haga.';
    }

    public function darPorBueno(): void
    {
        $d = $this->docOFalla();
        abort_unless($d->estado === 'borrador', 422);
        Conf::finalizar($d, false);
        $this->aviso = 'Hecho: el original se ha borrado del servidor. Ya puedes pedirle algo a Claude con el correo anonimizado.';
    }

    /** Vuelve a empezar con otro perfil o más nombres (borra el documento actual y su mapa). */
    public function descartar(): void
    {
        Conf::borrar($this->docOFalla());
        $this->aviso = 'Documento anonimizado y su mapa borrados.';
    }

    public function pedir(?string $texto = null): void
    {
        $d = $this->docOFalla();
        abort_unless(in_array($d->estado, ['borrador', 'listo'], true), 422);
        if ($texto !== null) {
            $this->instruccion = $texto;
        }
        $this->validate(['instruccion' => 'required|string|min:3|max:4000'], ['instruccion.required' => 'Escribe qué quieres que haga Claude.']);
        $nueva = ! $d->tarea_id;
        Conf::pedirAClaude($d, auth()->user(), $this->instruccion);
        $this->instruccion = '';
        $this->aviso = $nueva ? 'Petición enviada. Claude la recoge enseguida (suele tardar menos de un minuto); la respuesta sale aquí.' : 'Indicación enviada a Claude.';
    }

    /** El correo abierto (con caché breve: no se guarda en la página para no viajar en cada actualización). */
    protected function mensaje(): array
    {
        return $this->cache()->remember('correo_claude_msg_'.md5($this->buzon().'|'.$this->sel), 300, fn () => GraphMail::mensaje($this->buzon(), $this->sel));
    }

    /**
     * HTML del correo listo para un iframe aislado: sin scripts. Por defecto sin imágenes (así no se «avisa» al remitente de que se ha abierto);
     * con $imagenes las incrustadas (cid:) se ponen como datos del propio correo y se permiten las remotas (https).
     *
     * @param  array<string, string>  $cid  contentId => data URI
     */
    public static function documentoSeguro(array $m, array $cid = [], bool $imagenes = false): string
    {
        $cuerpo = ($m['tipo'] ?? 'html') === 'html' ? (string) $m['html'] : '<pre style="white-space:pre-wrap;font:inherit">'.e((string) $m['html']).'</pre>';
        $cuerpo = preg_replace('/(?is)<(script|iframe|object|embed|form)\b.*?<\/\1\s*>/', '', $cuerpo);   // con su contenido
        $cuerpo = preg_replace('/(?is)<\/?(script|iframe|object|embed|form|link|meta|base)\b[^>]*>/', '', $cuerpo);   // restos sin cierre
        if ($imagenes && $cid) {
            $cuerpo = preg_replace_callback('/cid:([^"\'\s)>]+)/i', fn ($x) => $cid[strtolower(trim($x[1], '<>'))] ?? $x[0], $cuerpo);
        }

        return '<!doctype html><html><head><meta charset="utf-8"><meta http-equiv="Content-Security-Policy" content="default-src \'none\'; style-src \'unsafe-inline\'; img-src data:'.($imagenes ? ' https:' : '').'">'
            .'<style>body{font:14px/1.45 Calibri,Arial,sans-serif;margin:12px;color:#222;word-wrap:break-word}img{max-width:100%}table{max-width:100%}</style></head><body>'.$cuerpo.'</body></html>';
    }

    /** Imágenes del correo como data URI, por su contentId (en minúsculas). Solo se piden a Graph si se ha pulsado «Mostrar imágenes». */
    protected function imagenesIncrustadas(): array
    {
        return $this->cache()->remember('correo_claude_img_'.md5($this->buzon().'|'.$this->sel), 300, function () {
            $out = [];
            foreach (GraphMail::adjuntos($this->buzon(), $this->sel) as $a) {
                if ($a['cid'] !== '' && str_starts_with($a['tipo'], 'image/') && strlen($a['bytes']) < 4_000_000) {
                    $out[strtolower($a['cid'])] = 'data:'.$a['tipo'].';base64,'.$a['bytes'];
                }
            }

            return $out;
        });
    }

    public function render()
    {
        $aviso = '';
        $sinPermiso = '';
        $lista = [];
        $cerrado = ! GraphMail::configurado();
        if (! $cerrado) {
            try {
                $lista = $this->cache()->remember($this->claveLista(), 30, fn () => GraphMail::recientes($this->buzon(), $this->max, $this->busqueda, $this->carpeta));
            } catch (\App\Support\GraphSinPermiso $e) {
                $sinPermiso = $e->getMessage();        // el buzón de este usuario aún no está en el grupo de Exchange de Appmos: el texto dice qué hacer
            } catch (\Throwable $e) {
                $aviso = 'No se ha podido leer el buzón ('.class_basename($e).').';
            }
        }
        $carpetas = ['plana' => [], 'inbox' => null, 'eliminados' => null];
        $destinos = [];
        if (! $cerrado) {
            try {
                $carpetas = $this->arbol();
                if ($this->mostrarMover) {
                    $f = mb_strtolower(trim($this->filtroCarpeta));
                    $destinos = array_slice(array_values(array_filter($this->todasLasCarpetas(), fn ($c) => $f === '' || str_contains(mb_strtolower($c['ruta']), $f))), 0, 40);
                }
            } catch (\Throwable $e) {
                $aviso = $aviso ?: 'No se han podido leer las carpetas ('.class_basename($e).').';
            }
        }
        $m = null;
        $adjuntos = [];
        if ($this->sel && ! $cerrado) {
            try {
                $m = $this->mensaje();
                $adjuntos = collect($this->cache()->remember('correo_claude_adj_'.md5($this->buzon().'|'.$this->sel), 300, fn () => GraphMail::adjuntosInfo($this->buzon(), $this->sel)))->reject(fn ($a) => $a['incrustado'])->values()->all();
            } catch (\Throwable $e) {
                $aviso = 'No se ha podido abrir ese correo.';
            }
        }
        $vistaAdj = null;
        if ($this->verAdj && $m) {
            try {
                $x = $this->cache()->remember('correo_claude_vista_'.md5($this->buzon().'|'.$this->sel.'|'.$this->verAdj), 300, fn () => GraphMail::adjuntoContenido($this->buzon(), $this->sel, $this->verAdj));
                if (str_starts_with($x['tipo'], 'image/') && strlen($x['bytes']) < 8_000_000) {
                    $vistaAdj = 'data:'.$x['tipo'].';base64,'.$x['bytes'];
                }
            } catch (\Throwable $e) {
                $this->aviso = 'No se ha podido abrir ese adjunto.';
            }
        }
        $doc = $this->doc();
        $texto = null;
        $imagenes = [];
        $conversacion = [];
        $progreso = [];
        $claro = $doc ? Conf::esClaro($doc) : false;
        $auto = $doc && $doc->estado === 'procesando' && ! empty(Conf::leerMeta($doc)['auto']);
        if ($doc && $doc->estado !== 'error') {
            foreach ($doc->archivos ?? [] as $n => $a) {
                if (str_ends_with($a, '.txt')) {
                    $texto .= (str_contains($a, '/') ? '— '.$a." —\n" : '').mb_substr((string) @file_get_contents(Conf::dir($doc).'/'.$a), 0, 20000)."\n\n";
                } else {
                    $imagenes[] = $n;
                }
            }
            $conversacion = Conf::conversacion($doc);
            $progreso = $doc->estado === 'procesando' ? Conf::progreso($doc) : [];
        }
        $hayRespuesta = false;
        $borradorAuto = $doc ? (Conf::leerMeta($doc)['borrador_auto'] ?? null) : null;
        foreach (array_reverse($conversacion) as $r) {
            if ($r['quien'] === 'claude') {
                $hayRespuesta = true;
                // las marcas ---BORRADOR--- / ---FIN---, cada una en su línea (no si Claude solo las nombra dentro de una frase)
                if (preg_match('/^[ \t]*-{3}\s*BORRADOR\s*-{3}[ \t]*\R(.*?)\R[ \t]*-{3}\s*FIN\s*-{3}[ \t]*$/msu', $r['texto'], $mm) && md5(trim($mm[1])) !== $this->bHash
                    && (Conf::leerMeta($doc)['borrador_auto']['hash'] ?? null) !== md5(trim($mm[1]))) {      // los ya creados solos no se vuelven a ofrecer
                    $this->bHash = md5(trim($mm[1]));
                    $this->bTexto = trim($mm[1]);
                    $this->bAsunto = (string) ($m['asunto'] ?? '');
                    $this->bHecho = '';
                }
                break;
            }
        }
        $esperaClaude = $doc && $doc->tarea_id && (count($conversacion) === 0 || end($conversacion)['quien'] === 'yo');
        $cid = [];
        if ($m && $this->verImagenes) {
            try {
                $cid = $this->imagenesIncrustadas();
            } catch (\Throwable $e) {
                $this->aviso = 'No se han podido traer las imágenes del correo.';
            }
        }
        $seguro = $m ? self::documentoSeguro($m, $cid, $this->verImagenes) : null;
        $this->aviso = $this->aviso ?: $aviso;

        return view('livewire.correo-claude', compact('lista', 'm', 'adjuntos', 'doc', 'texto', 'imagenes', 'conversacion', 'progreso', 'cerrado', 'esperaClaude', 'seguro', 'claro', 'carpetas', 'destinos', 'auto', 'sinPermiso', 'hayRespuesta', 'borradorAuto', 'vistaAdj'));
    }
}
