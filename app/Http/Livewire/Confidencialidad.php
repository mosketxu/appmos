<?php

namespace App\Http\Livewire;

use App\Models\ConfidencialidadDoc;
use App\Support\Confidencialidad as Conf;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Confidencialidad (17-nov-2026): se sube un documento, el VPS lo anonimiza (etiquetas Persona1, Empresa1, Mail1…, relación guardada solo aquí),
 * se escribe qué se quiere que haga Claude y se crea una tarea del TO-DO que Claude recoge ya con el documento anonimizado. Su respuesta
 * se muestra aquí con los datos reales puestos. Solo Admin. Ver Confidencialidad/LEEME.md.
 */
class Confidencialidad extends Component
{
    use WithFileUploads;

    public array $subidas = [];
    public string $nombres = '';
    public string $empresas = '';
    public string $perfil = 'estricto';
    public string $titulo = '';
    public ?int $docSel = null;
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

    protected function lineas(string $t): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/u', $t) ?: []), fn ($x) => mb_strlen($x) >= 3));
    }

    public function anonimizar(): void
    {
        $this->aviso = '';
        $this->validate([
            'subidas' => 'required|array|min:1|max:10',
            'subidas.*' => 'file|max:30720|extensions:'.implode(',', Conf::EXTENSIONES),
            'perfil' => 'required|in:estricto,contable',
            'titulo' => 'nullable|string|max:120',
        ], ['subidas.required' => 'Elige al menos un documento.', 'subidas.*.extensions' => 'Tipo de fichero no admitido.', 'subidas.*.max' => 'Cada documento puede pesar hasta 30 MB.']);
        $d = Conf::crear(auth()->user(), $this->subidas, $this->lineas($this->nombres), $this->lineas($this->empresas), $this->perfil, trim($this->titulo));
        $this->subidas = [];
        $this->docSel = $d->id;
        $this->aviso = $d->estado === 'error' ? '' : 'Documento anonimizado. Revisa la vista previa antes de pedir nada a Claude.';
    }

    public function seleccionar(int $id): void
    {
        $this->docSel = ConfidencialidadDoc::where('user_id', auth()->id())->where('id', $id)->exists() ? $id : null;
        $this->instruccion = '';
        $this->aviso = '';
    }

    public function pedir(): void
    {
        $d = ConfidencialidadDoc::where('user_id', auth()->id())->where('estado', 'listo')->findOrFail($this->docSel);
        $this->validate(['instruccion' => 'required|string|min:3|max:4000'], ['instruccion.required' => 'Escribe qué quieres que haga Claude.']);
        $nueva = ! $d->tarea_id;
        Conf::pedirAClaude($d, auth()->user(), $this->instruccion);
        $this->instruccion = '';
        $this->aviso = $nueva ? 'Petición enviada. Claude la recoge en unos minutos; la respuesta saldrá aquí abajo.' : 'Indicación enviada a Claude.';
    }

    public function borrar(int $id): void
    {
        $d = ConfidencialidadDoc::where('user_id', auth()->id())->findOrFail($id);
        Conf::borrar($d);
        if ($this->docSel === $id) {
            $this->docSel = null;
        }
        $this->aviso = 'Documento y mapa borrados. La tarea del TO-DO (si la hay) se queda, ya sin posibilidad de recuperar los datos reales.';
    }

    public function render()
    {
        $docs = ConfidencialidadDoc::where('user_id', auth()->id())->latest('id')->take(30)->get();
        $sel = $this->docSel ? $docs->firstWhere('id', $this->docSel) : null;
        $texto = null;
        $imagenes = [];
        $respuestas = [];
        if ($sel && $sel->estado === 'listo') {
            foreach ($sel->archivos ?? [] as $n => $a) {
                if (str_ends_with($a, '.txt')) {
                    $texto .= (str_contains($a, '/') ? '— '.$a." —\n" : '').mb_substr((string) @file_get_contents(Conf::dir($sel).'/'.$a), 0, 20000)."\n\n";
                } else {
                    $imagenes[] = $n;
                }
            }
            $respuestas = Conf::respuestas($sel);
        }

        return view('livewire.confidencialidad', compact('docs', 'sel', 'texto', 'imagenes', 'respuestas'));
    }
}
