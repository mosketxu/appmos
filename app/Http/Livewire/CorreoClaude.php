<?php

namespace App\Http\Livewire;

use App\Models\ConfidencialidadDoc;
use App\Support\Confidencialidad as Conf;
use App\Support\GraphMail;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

/**
 * «✉ Correo» (19-nov-2026): bandeja de entrada a la izquierda, el correo en el centro (se ve en claro SOLO aquí, nunca sale hacia Anthropic) y a la derecha lo que se quiere hacer con él.
 * Para trabajar con Claude el correo (cuerpo + adjuntos) pasa por el mismo anonimizador que la pantalla Confidencialidad (se reutiliza todo): primera pasada automática,
 * «dar por bueno» (borra el original) y chat con Claude, que solo ve lo anonimizado. Solo Admin (permiso confidencialidad.ver).
 */
class CorreoClaude extends Component
{
    public string $busqueda = '';
    public int $max = 40;
    public ?string $sel = null;
    public string $perfil = 'estricto';
    public string $nombres = '';
    public string $empresas = '';
    public string $instruccion = '';
    public string $aviso = '';

    public function hydrate(): void
    {
        abort_unless(auth()->user()?->can('confidencialidad.ver'), 403);
    }

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('confidencialidad.ver'), 403);
    }

    protected function buzon(): string
    {
        return (string) config('contabilidad.graph.sender');
    }

    protected function lineas(string $t): array
    {
        return array_values(array_unique(array_filter(array_map('trim', preg_split('/\R/u', $t) ?: []), fn ($x) => mb_strlen($x) >= 3)));
    }

    public function seleccionar(string $id): void
    {
        $this->sel = $id;
        $this->instruccion = '';
        $this->aviso = '';
    }

    public function masCorreos(): void
    {
        $this->max = min($this->max + 40, 200);
    }

    public function refrescar(): void
    {
        Cache::forget($this->claveLista());
        $this->aviso = '';
    }

    protected function claveLista(): string
    {
        return 'correo_claude_lista_'.md5($this->buzon().'|'.$this->max.'|'.mb_strtolower(trim($this->busqueda)));
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
    public function preparar(): void
    {
        $this->aviso = '';
        abort_unless($this->sel, 422);
        $this->validate(['perfil' => 'required|in:estricto,contable,mercantil']);
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
        $d = Conf::crear(auth()->user(), array_slice($archivos, 0, 25), $this->lineas($this->nombres), $this->lineas($this->empresas), $this->perfil, mb_substr('Correo: '.($m['asunto'] ?? ''), 0, 120));
        $d->update(['correo_id' => $this->sel]);
        $this->nombres = $this->empresas = '';
        $this->aviso = $d->estado === 'error' ? '' : 'Anonimizando en segundo plano (los adjuntos escaneados tardan). Cuando termine podrás revisarlo.';
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
        $this->aviso = 'Listo: el correo irá a Claude tal cual. '.($omitidos ? 'No se incluyen estos adjuntos (solo PDF, imágenes y texto): '.implode(', ', $omitidos).'. ' : '').'Escribe qué quieres que haga.';
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
        $this->aviso = $nueva ? 'Petición enviada. Claude la recoge en unos minutos; la respuesta saldrá aquí.' : 'Indicación enviada a Claude.';
    }

    /** El correo abierto (con caché breve: no se guarda en la página para no viajar en cada actualización). */
    protected function mensaje(): array
    {
        return Cache::remember('correo_claude_msg_'.md5($this->buzon().'|'.$this->sel), 300, fn () => GraphMail::mensaje($this->buzon(), $this->sel));
    }

    /** HTML del correo listo para un iframe aislado: sin scripts, sin imágenes ni nada remoto (así no se «avisa» al remitente de que se ha abierto). */
    public static function documentoSeguro(array $m): string
    {
        $cuerpo = ($m['tipo'] ?? 'html') === 'html' ? (string) $m['html'] : '<pre style="white-space:pre-wrap;font:inherit">'.e((string) $m['html']).'</pre>';
        $cuerpo = preg_replace('/(?is)<(script|iframe|object|embed|form)\b.*?<\/\1\s*>/', '', $cuerpo);   // con su contenido
        $cuerpo = preg_replace('/(?is)<\/?(script|iframe|object|embed|form|link|meta|base)\b[^>]*>/', '', $cuerpo);   // restos sin cierre

        return '<!doctype html><html><head><meta charset="utf-8"><meta http-equiv="Content-Security-Policy" content="default-src \'none\'; style-src \'unsafe-inline\'; img-src data:">'
            .'<style>body{font:14px/1.45 Calibri,Arial,sans-serif;margin:12px;color:#222;word-wrap:break-word}img{max-width:100%}table{max-width:100%}</style></head><body>'.$cuerpo.'</body></html>';
    }

    public function render()
    {
        $aviso = '';
        $lista = [];
        $cerrado = ! GraphMail::configurado();
        if (! $cerrado) {
            try {
                $lista = Cache::remember($this->claveLista(), 30, fn () => GraphMail::recientes($this->buzon(), $this->max, $this->busqueda));
            } catch (\Throwable $e) {
                $aviso = 'No se ha podido leer el buzón ('.class_basename($e).').';
            }
        }
        $m = null;
        $adjuntos = [];
        if ($this->sel && ! $cerrado) {
            try {
                $m = $this->mensaje();
                $adjuntos = collect(Cache::remember('correo_claude_adj_'.md5($this->buzon().'|'.$this->sel), 300, fn () => GraphMail::adjuntosInfo($this->buzon(), $this->sel)))->reject(fn ($a) => $a['incrustado'])->values()->all();
            } catch (\Throwable $e) {
                $aviso = 'No se ha podido abrir ese correo.';
            }
        }
        $doc = $this->doc();
        $texto = null;
        $imagenes = [];
        $conversacion = [];
        $progreso = [];
        $claro = $doc ? Conf::esClaro($doc) : false;
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
        $esperaClaude = $doc && $doc->tarea_id && (count($conversacion) === 0 || end($conversacion)['quien'] === 'yo');
        $seguro = $m ? self::documentoSeguro($m) : null;
        $this->aviso = $this->aviso ?: $aviso;

        return view('livewire.correo-claude', compact('lista', 'm', 'adjuntos', 'doc', 'texto', 'imagenes', 'conversacion', 'progreso', 'cerrado', 'esperaClaude', 'seguro', 'claro'));
    }
}
