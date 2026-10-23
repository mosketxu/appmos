<?php

namespace App\Http\Livewire;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Formación (22-oct-2026): listado de cosas que Alex quiere explicar a sus compañeros. Ven todos; edita quien tenga formacion.editar.
 */
class Formacion extends Component
{
    use WithFileUploads;

    /** Archivos que se acaban de elegir o arrastrar, y el tema al que van. */
    public array $subidas = [];
    public ?int $temaSubida = null;
    /** Archivo que se ve a la derecha. */
    public ?int $archivoSel = null;
    public string $errorArchivos = '';

    public string $titulo = '';
    public string $descripcion = '';
    public ?int $editando = null;
    /** El formulario solo se ve al pulsar «Nuevo» o al editar un tema. */
    public bool $formulario = false;
    public string $filtro = 'todos'; // todos | pendiente | explicado

    public function hydrate(): void
    {
        $this->autorizar();
    }

    protected function autorizar(bool $editar = false): void
    {
        abort_unless(auth()->user()?->can($editar ? 'formacion.editar' : 'formacion.ver'), 403);
    }

    public function mount(): void
    {
        $this->autorizar();
    }

    public function guardar(): void
    {
        $this->autorizar(true);
        $this->validate(['titulo' => 'required|string|max:200', 'descripcion' => 'nullable|string|max:5000']);
        $datos = ['titulo' => trim($this->titulo), 'descripcion' => trim($this->descripcion) ?: null, 'updated_at' => now()];
        if ($this->editando) {
            DB::table('formacion_temas')->where('id', $this->editando)->update($datos);
        } else {
            DB::table('formacion_temas')->insert($datos + ['orden' => (int) DB::table('formacion_temas')->max('orden') + 1, 'created_at' => now()]);
        }
        $this->cancelar();
    }

    public function nuevo(): void
    {
        $this->autorizar(true);
        $this->cancelar();
        $this->formulario = true;
    }

    public function editar(int $id): void
    {
        $this->autorizar(true);
        if ($t = DB::table('formacion_temas')->find($id)) {
            $this->editando = $id;
            $this->formulario = true;
            $this->titulo = $t->titulo;
            $this->descripcion = (string) $t->descripcion;
        }
    }

    public function cancelar(): void
    {
        $this->reset('titulo', 'descripcion', 'editando', 'formulario');
        $this->resetValidation();
    }

    public function alternarEstado(int $id): void
    {
        $this->autorizar(true);
        $t = DB::table('formacion_temas')->find($id);
        if ($t) {
            $explicado = $t->estado !== 'explicado';
            DB::table('formacion_temas')->where('id', $id)->update([
                'estado' => $explicado ? 'explicado' : 'pendiente',
                'fecha_explicado' => $explicado ? now()->toDateString() : null,
                'updated_at' => now(),
            ]);
        }
    }

    public function mover(int $id, int $dir): void
    {
        $this->autorizar(true);
        $ids = DB::table('formacion_temas')->orderBy('orden')->orderBy('id')->pluck('id')->all();
        $i = array_search($id, $ids, true);
        $j = $i === false ? false : $i + ($dir < 0 ? -1 : 1);
        if ($i === false || ! isset($ids[$j])) {
            return;
        }
        [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
        foreach ($ids as $n => $tid) {
            DB::table('formacion_temas')->where('id', $tid)->update(['orden' => $n + 1]);
        }
    }

    /** Se llama sola al elegir o arrastrar archivos: los guarda en el tema y enseña el último a la derecha. */
    public function updatedSubidas(): void
    {
        $this->autorizar(true);
        $this->errorArchivos = '';
        $tema = $this->temaSubida;
        if (! $tema || ! DB::table('formacion_temas')->where('id', $tema)->exists()) {
            $this->subidas = [];
            $this->errorArchivos = 'No sé a qué tema van los archivos: arrástralos sobre un tema o usa su botón «Añadir archivos».';
            return;
        }
        $n = 0;
        foreach ($this->subidas as $f) {
            if ($f->getSize() > 51200 * 1024) {
                $this->errorArchivos = 'Hay archivos de más de 50 MB y no se han subido.';
                continue;
            }
            $nombre = basename(str_replace('\\', '/', $f->getClientOriginalName())) ?: 'archivo';
            $ext = preg_replace('/[^a-z0-9]/', '', strtolower(pathinfo($nombre, PATHINFO_EXTENSION)));
            $ruta = $f->storeAs("formacion/{$tema}", Str::random(24).($ext ? ".{$ext}" : ''), 'local');
            $this->archivoSel = DB::table('formacion_archivos')->insertGetId([
                'tema_id' => $tema, 'nombre' => $nombre, 'ruta' => $ruta, 'extension' => substr($ext, 0, 12), 'tamano' => $f->getSize(),
                'user_id' => auth()->id(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $n++;
        }
        $this->subidas = [];
    }

    public function seleccionar(int $id): void
    {
        $this->autorizar();
        $this->archivoSel = $this->archivoSel === $id ? null : $id;
    }

    public function quitarArchivo(int $id): void
    {
        $this->autorizar(true);
        if ($a = DB::table('formacion_archivos')->find($id)) {
            Storage::disk('local')->delete($a->ruta);
            DB::table('formacion_archivos')->where('id', $id)->delete();
        }
        if ($this->archivoSel === $id) {
            $this->archivoSel = null;
        }
    }

    public function borrar(int $id): void
    {
        $this->autorizar(true);
        foreach (DB::table('formacion_archivos')->where('tema_id', $id)->get() as $a) {
            $this->quitarArchivo($a->id);
        }
        DB::table('formacion_temas')->where('id', $id)->delete();
        if ($this->editando === $id) {
            $this->cancelar();
        }
    }

    public function render()
    {
        $q = DB::table('formacion_temas')->orderBy('orden')->orderBy('id');
        $total = (clone $q)->count();
        $explicados = (clone $q)->where('estado', 'explicado')->count();
        if (in_array($this->filtro, ['pendiente', 'explicado'], true)) {
            $q->where('estado', $this->filtro);
        }
        $archivos = DB::table('formacion_archivos')->orderBy('id')->get()->groupBy('tema_id');

        return view('livewire.formacion', [
            'archivos' => $archivos,
            'actual' => $this->archivoSel ? DB::table('formacion_archivos')->find($this->archivoSel) : null,
            'temas' => $q->get(),
            'total' => $total,
            'explicados' => $explicados,
            'puedeEditar' => (bool) auth()->user()?->can('formacion.editar'),
        ]);
    }
}
