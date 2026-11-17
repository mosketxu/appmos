<?php

namespace App\Http\Livewire;

use App\Models\ConfidencialidadDoc;
use App\Support\Confidencialidad as Conf;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Confidencialidad (17-nov-2026): se sube un documento, el VPS hace una primera anonimización automática (etiquetas Persona1, Empresa1, Mail1…, relación guardada
 * solo aquí) y se ve el ORIGINAL junto al ANONIMIZADO para afinar qué más ocultar (con sugerencias locales y, si se quiere, la revisión de Claude sobre lo ya anonimizado).
 * Al darlo por bueno se borra el original; entonces se le dice a Claude qué hacer (tarea del TO-DO) y su respuesta se muestra con los datos reales. Solo Admin.
 */
class Confidencialidad extends Component
{
    use WithFileUploads;

    public array $subidas = [];
    public string $nombres = '';
    public string $empresas = '';
    public string $perfil = 'estricto';
    public string $titulo = '';
    #[\Livewire\Attributes\Url(as: 'doc', history: false)]   // en la dirección: con F5 se vuelve a abrir el mismo documento
    public ?int $docSel = null;
    public string $instruccion = '';
    public string $aviso = '';
    /** Búsqueda de un correo del buzón por asunto/texto (Graph; solo lectura). */
    public string $consultaCorreo = '';
    public array $resultadosCorreo = [];
    /** Lo que se oculta en el documento abierto (se editan aquí y se aplican con «Actualizar»). */
    public string $edNombres = '';
    public string $edEmpresas = '';
    public string $edPerfil = 'estricto';

    public function hydrate(): void
    {
        abort_unless(auth()->user()?->can('confidencialidad.ver'), 403);
    }

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('confidencialidad.ver'), 403);
        Conf::limpiarAbandonados();
        if ($this->docSel) {
            $d = ConfidencialidadDoc::where('user_id', auth()->id())->where('id', $this->docSel)->first();
            $this->docSel = $d?->id;
            if ($d) {
                $this->cargarListas($d);
            }
        }
    }

    protected function lineas(string $t): array
    {
        return array_values(array_unique(array_filter(array_map('trim', preg_split('/\R/u', $t) ?: []), fn ($x) => mb_strlen($x) >= 3)));
    }

    protected function doc(?int $id = null): ConfidencialidadDoc
    {
        return ConfidencialidadDoc::where('user_id', auth()->id())->findOrFail($id ?? $this->docSel);
    }

    protected function cargarListas(ConfidencialidadDoc $d): void
    {
        $m = Conf::leerMeta($d);
        $this->edNombres = implode("\n", $m['nombres']);
        $this->edEmpresas = implode("\n", $m['empresas']);
        $this->edPerfil = $m['perfil'];
    }

    /** Al terminar de subir, se anonimiza solo (sin botón): enseguida salen los visores. */
    public function updatedSubidas(): void
    {
        if (count(array_filter((array) $this->subidas))) {
            $this->anonimizar();
        }
    }

    public function anonimizar(): void
    {
        $this->aviso = '';
        $this->validate([
            'subidas' => 'required|array|min:1|max:10',
            'subidas.*' => 'file|max:30720|extensions:'.implode(',', Conf::EXTENSIONES),
            'perfil' => 'required|in:estricto,contable,mercantil',
            'titulo' => 'nullable|string|max:120',
        ], ['subidas.required' => 'Elige al menos un documento.', 'subidas.*.extensions' => 'Tipo de fichero no admitido.', 'subidas.*.max' => 'Cada documento puede pesar hasta 30 MB.']);
        $archivos = [];
        foreach ($this->subidas as $f) {               // un correo .eml se abre: su cuerpo y sus adjuntos pasan a ser documentos sueltos
            if (strtolower($f->getClientOriginalExtension()) === 'eml') {
                try {
                    array_push($archivos, ...Conf::expandirEml($f->getRealPath()));
                } finally {
                    $f->delete();
                }
            } else {
                $archivos[] = $f;
            }
        }
        $primero = pathinfo($this->subidas[0]->getClientOriginalName(), PATHINFO_FILENAME);   // título por defecto: el nombre del fichero
        $titulo = trim($this->titulo) ?: (count($this->subidas) > 1 ? $primero.' y '.(count($this->subidas) - 1).' más' : $primero);
        if (! Conf::almacenAbierto()) {
            $this->aviso = Conf::MENSAJE_CERRADO;

            return;
        }
        $d = Conf::crear(auth()->user(), array_slice($archivos, 0, 25), $this->lineas($this->nombres), $this->lineas($this->empresas), $this->perfil, mb_substr($titulo, 0, 120));
        $this->subidas = [];
        $this->nombres = $this->empresas = $this->titulo = '';
        $this->docSel = $d->id;
        $this->cargarListas($d);
        $this->aviso = $d->estado === 'error' ? '' : 'Subido. Se está anonimizando en segundo plano (los escaneados tardan: abajo verás el progreso). Mientras tanto ya puedes mirar el original.';
    }

    protected function buzon(): string
    {
        return (string) config('contabilidad.graph.sender');
    }

    public function buscarCorreo(): void
    {
        $this->aviso = '';
        $this->resultadosCorreo = [];
        if (trim($this->consultaCorreo) === '') {
            return;
        }
        if (! \App\Support\GraphMail::configurado()) {
            $this->aviso = 'El correo (Graph) no está configurado en este servidor.';

            return;
        }
        try {
            $this->resultadosCorreo = \App\Support\GraphMail::buscarMensajes($this->buzon(), $this->consultaCorreo, 15);
        } catch (\Throwable $e) {
            $this->aviso = 'No se ha podido buscar en el correo.';
        }
        if (! $this->resultadosCorreo && ! $this->aviso) {
            $this->aviso = 'No hay correos con ese texto.';
        }
    }

    /** Trae el correo (cuerpo + adjuntos, también imágenes incrustadas) y lo anonimiza como cualquier documento. */
    public function usarCorreo(string $id): void
    {
        $this->aviso = '';
        $m = collect($this->resultadosCorreo)->firstWhere('id', $id);
        abort_unless($m, 422);
        $archivos = Conf::archivosDeCorreo($this->buzon(), $id);
        if (! Conf::almacenAbierto()) {
            $this->aviso = Conf::MENSAJE_CERRADO;

            return;
        }
        $d = Conf::crear(auth()->user(), array_slice($archivos, 0, 25), $this->lineas($this->nombres), $this->lineas($this->empresas), $this->perfil, trim($this->titulo) ?: 'Correo');
        $this->resultadosCorreo = [];
        $this->consultaCorreo = '';
        $this->docSel = $d->id;
        $this->cargarListas($d);
        $this->aviso = $d->estado === 'error' ? '' : 'Correo traído. Se está anonimizando en segundo plano: ya puedes mirar el original y, al terminar, afinar y dar por bueno.';
    }

    public function seleccionar(int $id): void
    {
        $d = ConfidencialidadDoc::where('user_id', auth()->id())->where('id', $id)->first();
        $this->docSel = $d?->id;
        $this->instruccion = '';
        $this->aviso = '';
        if ($d) {
            $this->cargarListas($d);
        }
    }

    /** Añade una sugerencia a la lista de personas o empresas (se aplica con «Actualizar»). */
    public function agregar(string $texto, string $tipo): void
    {
        $texto = trim($texto);
        if (mb_strlen($texto) < 3) {
            return;
        }
        if ($tipo === 'empresa') {
            $this->edEmpresas = trim($this->edEmpresas."\n".$texto);
        } else {
            $this->edNombres = trim($this->edNombres."\n".$texto);
        }
    }

    public function actualizar(): void
    {
        $d = $this->doc();
        abort_unless($d->estado === 'borrador', 422);
        $d = Conf::actualizar($d, $this->lineas($this->edNombres), $this->lineas($this->edEmpresas), $this->edPerfil);
        $this->aviso = $d->estado === 'error' ? '' : 'Rehaciendo el anonimizado en segundo plano…';
    }

    public bool $conservarOriginal = false;

    /** Recuadro manual: texto leído bajo lo dibujado (editable) y página. */
    public string $recuadroTexto = '';
    public ?int $recuadroPagina = null;
    public bool $recuadroHecho = false;

    public function recuadro(int $pagina, float $x1, float $y1, float $x2, float $y2): void
    {
        $d = $this->doc();
        abort_unless($d->estado === 'borrador', 422);
        $this->recuadroTexto = Conf::textoEnRecuadro($d, $pagina, $x1, $y1, $x2, $y2);
        $this->recuadroPagina = $pagina;
        $this->recuadroHecho = true;
    }

    /** ¿Alguna línea de las listas de personas/empresas cubre este dato real? (para ofrecer «destapar»). */
    public function enListas(string $real): bool
    {
        $r = mb_strtolower(trim($real));

        return $r !== '' && collect(array_merge($this->lineas($this->edNombres), $this->lineas($this->edEmpresas)))
            ->contains(fn ($l) => ($x = mb_strtolower($l)) === $r || str_contains($x, $r) || str_contains($r, $x));
    }

    /** Destapar: quita de las listas lo que cubre ese dato y rehace el anonimizado (los datos detectados solos —IBAN, correo…— no se destapan desde aquí). */
    public function destapar(string $real): void
    {
        $d = $this->doc();
        abort_unless($d->estado === 'borrador', 422);
        $r = mb_strtolower(trim($real));
        $quitar = fn ($l) => ($x = mb_strtolower($l)) === $r || str_contains($x, $r) || str_contains($r, $x);
        $this->edNombres = implode("\n", array_filter($this->lineas($this->edNombres), fn ($l) => ! $quitar($l)));
        $this->edEmpresas = implode("\n", array_filter($this->lineas($this->edEmpresas), fn ($l) => ! $quitar($l)));
        Conf::ponerVisibles($d, array_merge(Conf::visibles($d), [$real]));   // también lo detectado solo (IBAN, correo, CIF…): se deja a la vista
        $this->actualizar();
    }

    /** Volver a tapar algo que se había dejado a la vista. */
    public function taparOtraVez(string $real): void
    {
        $d = $this->doc();
        abort_unless($d->estado === 'borrador', 422);
        Conf::ponerVisibles($d, array_filter(Conf::visibles($d), fn ($v) => mb_strtolower(trim($v)) !== mb_strtolower(trim($real))));
        $this->actualizar();
    }

    public function cancelarRecuadro(): void
    {
        $this->recuadroTexto = '';
        $this->recuadroPagina = null;
        $this->recuadroHecho = false;
    }

    /** Tapa lo que hay bajo el recuadro en TODO el documento: se añade a las listas, entra en «Quién es quién» y se rehace (rápido, con la caché). */
    public function taparRecuadro(string $tipo): void
    {
        $texto = trim($this->recuadroTexto);
        if (mb_strlen($texto) < 3) {
            $this->aviso = 'Escribe el nombre que hay bajo el recuadro (mínimo 3 letras).';

            return;
        }
        $this->agregar($texto, $tipo === 'empresa' ? 'empresa' : 'persona');
        $this->cancelarRecuadro();
        $this->actualizar();
    }


    public function darPorBueno(): void
    {
        $d = $this->doc();
        abort_unless($d->estado === 'borrador', 422);
        Conf::finalizar($d, $this->conservarOriginal);
        $this->aviso = $this->conservarOriginal
            ? 'Hecho. El original se queda guardado en el servidor '.Conf::CONSERVA_DIAS.' días (o hasta que borres el documento). Ya puedes pedirle algo a Claude con el documento anonimizado.'
            : 'Hecho: el original se ha borrado del servidor. Ya puedes pedirle algo a Claude con el documento anonimizado.';
        $this->conservarOriginal = false;
    }

    /** Pide a Claude que mire SOLO lo anonimizado y diga qué datos personales siguen a la vista (sus respuestas se convierten en sugerencias). */
    public function revisionClaude(): void
    {
        $d = $this->doc();
        abort_unless(in_array($d->estado, ['borrador', 'listo'], true), 422);
        Conf::pedirAClaude($d, auth()->user(),
            'REVISIÓN DE FUGAS. Empieza tu respuesta con la palabra REVISIÓN en una línea sola. Después mira el documento anonimizado y lista, una por línea con el formato «- texto exacto tal como aparece», todo lo que SIGA a la vista y pueda identificar a una persona '
            .'o a una empresa (nombres propios, direcciones, números de identificación, lugares muy concretos, etc.). No incluyas las etiquetas (Persona1, Empresa1…) ni importes ni fechas. '
            .'Si no ves ninguno, responde solo «Sin datos visibles». No añadas explicaciones.');
        $this->aviso = 'Revisión pedida a Claude (solo ve lo anonimizado). En unos minutos aparecerán abajo sus sugerencias.';
    }

    public function pedir(): void
    {
        $d = $this->doc();
        abort_unless(in_array($d->estado, ['borrador', 'listo'], true), 422);
        $this->validate(['instruccion' => 'required|string|min:3|max:4000'], ['instruccion.required' => 'Escribe qué quieres que haga Claude.']);
        $nueva = ! $d->tarea_id;
        Conf::pedirAClaude($d, auth()->user(), $this->instruccion);
        $this->instruccion = '';
        $this->aviso = $nueva ? 'Petición enviada. Claude la recoge en unos minutos; la respuesta saldrá aquí abajo.' : 'Indicación enviada a Claude.';
    }

    public function borrar(int $id): void
    {
        $d = $this->doc($id);
        Conf::borrar($d);
        if ($this->docSel === $id) {
            $this->docSel = null;
        }
        $this->aviso = 'Documento y mapa borrados. La conversación con Claude se queda con etiquetas, ya sin posibilidad de recuperar los datos reales.';
    }

    public function render()
    {
        $docs = ConfidencialidadDoc::where('user_id', auth()->id())->latest('id')->take(30)->get();
        $sel = $this->docSel ? $docs->firstWhere('id', $this->docSel) : null;
        $texto = null;
        $imagenes = [];
        $respuestas = [];
        $conversacion = [];
        $originales = [];
        $candidatos = [];
        $deClaude = [];
        $quien = $sel && in_array($sel->estado, ['borrador', 'listo'], true) ? Conf::quienEsQuien($sel) : [];
        $progreso = $sel && $sel->estado === 'procesando' ? Conf::progreso($sel) : [];
        $hayProcesando = $docs->contains('estado', 'procesando');
        $almacenCerrado = ! Conf::almacenAbierto();
        $visibles = $sel && $sel->estado === 'borrador' ? Conf::visibles($sel) : [];
        if ($sel && $sel->estado !== 'error') {
            foreach ($sel->archivos ?? [] as $n => $a) {
                if (str_ends_with($a, '.txt')) {
                    $texto .= (str_contains($a, '/') ? '— '.$a." —\n" : '').mb_substr((string) @file_get_contents(Conf::dir($sel).'/'.$a), 0, 20000)."\n\n";
                } else {
                    $imagenes[] = $n;
                }
            }
            $respuestas = Conf::respuestas($sel);
            $conversacion = Conf::conversacion($sel);
            if (in_array($sel->estado, ['procesando', 'borrador'], true)) {
                foreach (Conf::leerMeta($sel)['originales'] as $o) {
                    $vista = Conf::dir($sel).'/_orig/'.$o['n'].'.vista.txt';
                    $originales[] = ['n' => $o['n'], 'nombre' => $o['nombre'], 'ext' => $o['ext'],
                        'tipo' => in_array($o['ext'], ['pdf'], true) ? 'pdf' : (in_array($o['ext'], ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp'], true) ? 'imagen' : 'texto'),
                        'texto' => is_file($vista) ? mb_substr((string) file_get_contents($vista), 0, 30000) : null];
                }
                $candidatos = $sel->estado === 'borrador' ? array_slice(Conf::candidatos($sel), 0, 10) : [];
            }
            // Sugerencias de la revisión de Claude: líneas «- texto» de su última respuesta
            $ultima = '';
            foreach (array_reverse($respuestas) as $r) {
                if (preg_match('/^\s*REVISI[ÓO]N/iu', $r['texto'])) {
                    $ultima = $r['texto'];
                    break;
                }
            }
            foreach (preg_split('/\R/u', $ultima) as $l) {
                if (preg_match('/^\s*[-•*]\s+(.{3,120})$/u', $l, $m) && stripos($l, 'sin datos visibles') === false) {
                    $deClaude[] = trim($m[1], " «»\"'");
                }
            }
            $deClaude = array_values(array_unique($deClaude));
        }

        return view('livewire.confidencialidad', compact('docs', 'sel', 'texto', 'imagenes', 'respuestas', 'conversacion', 'originales', 'candidatos', 'deClaude', 'progreso', 'hayProcesando', 'quien', 'almacenCerrado', 'visibles'));
    }
}
