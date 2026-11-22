<?php

namespace App\Http\Livewire;

use App\Models\Pu as ModelPu;
use App\Models\PuAdjunto;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

class Pu extends Component
{
    use WithFileUploads;

    /** Ficheros/imágenes elegidos (o pegados) en el modal; se guardan al pulsar Save (22-nov-2026). */
    public array $subidas = [];
    /** Lo que llega del selector, de arrastrar o de pegar (Ctrl+V): se añade a $subidas sin borrar lo anterior. */
    public array $nuevas = [];
    public $entidad;
    public $filtroentidad;
    public $search='';
    public $showEditModal = false;
    public $showDeleteModal = false;
    public $ruta='entidad.pu';
    public $editing = [];
    public $editingId = null;

    public function rules() {
        return [
            'editing.entidad_id' => 'nullable|numeric',
            'editing.destino' => 'nullable',
            'editing.url' => 'nullable',
            'editing.us' => 'nullable',
            'editing.us2' => 'nullable',
            'editing.ps' => 'nullable',
            'editing.observaciones' => 'nullable',
            'subidas.*' => 'file|max:20480',
        ];
    }

    public function mount() {
        $this->editing = $this->makeBlankPu();
    }


    public function render(){
        $ent=$this->entidad;
        $i=$this->search;
        $pus=ModelPu::where('entidad_id',$this->entidad->id)
            ->when($this->search !='',function ($query) use($i){
                $query->where(function($q) use ($i){
                    $q->where('destino','like','%'.$this->search.'%')
                        ->orWhere('url','like','%'.$this->search.'%')
                        ->orWhere('us','like','%'.$this->search.'%')
                        ->orWhere('us2','like','%'.$this->search.'%')
                        ->orWhere('observaciones','like','%'.$this->search.'%');
                    });
                })
            ->with('adjuntos')
            ->orderBy('destino')
            ->get();
        $adjuntosEdicion = $this->editingId ? PuAdjunto::where('pu_id', $this->editingId)->orderBy('id')->get() : collect();
        return view('livewire.pu',compact('ent','pus','adjuntosEdicion'));
    }


    public function makeBlankPu(){
        $this->editingId = null;

        return [
            'destino' => '',
            'url' => '',
            'us' => '',
            'us2' => '',
            'ps' => '',
            'observaciones' => '',
        ];
    }

    public function create(){
        if ($this->editingId) $this->editing = $this->makeBlankPu();
        $this->subidas = [];
        $this->showEditModal = true;
    }

    public function edit(ModelPu $pu){
        if ($this->editingId !== $pu->id) {
            $this->editing = $pu->only(['entidad_id', 'destino', 'url', 'us', 'us2', 'ps', 'observaciones']);
            $this->editingId = $pu->id;
            $this->subidas = [];
        }
        $this->showEditModal = true;
    }

    public function save(){
        abort_unless(auth()->user()?->can('entidades.editar'), 403);
        $this->validate($this->rules(), ['subidas.*.max' => 'Algún fichero pesa más de 20 MB.', 'subidas.*.file' => 'No se ha podido subir algún fichero.']);
        $this->editing['entidad_id'] = $this->entidad->id;
        $pu = ModelPu::updateOrCreate(['id' => $this->editingId], $this->editing);
        $this->guardarSubidas($pu);
        $this->showEditModal = false;
    }

    /** Guarda en storage/app/pus/<entidad> los ficheros pendientes del modal. */
    protected function guardarSubidas(ModelPu $pu): void
    {
        foreach ($this->subidas as $f) {
            $nombre = mb_substr(basename(str_replace('\\', '/', (string) $f->getClientOriginalName())), -200) ?: 'fichero';
            $ext = substr(preg_replace('/[^a-z0-9]/', '', strtolower(pathinfo($nombre, PATHINFO_EXTENSION))), 0, 8);
            $ruta = $f->storeAs('pus/'.$pu->entidad_id, Str::random(32).($ext !== '' ? '.'.$ext : ''), 'local');
            PuAdjunto::create(['pu_id' => $pu->id, 'nombre' => $nombre, 'ruta' => $ruta, 'tamano' => $f->getSize(), 'user_id' => auth()->id()]);
        }
        $this->subidas = [];
    }

    public function updatedNuevas(): void
    {
        abort_unless(auth()->user()?->can('entidades.editar'), 403);
        $this->validate(['nuevas.*' => 'file|max:20480'], ['nuevas.*.max' => 'Algún fichero pesa más de 20 MB.', 'nuevas.*.file' => 'No se ha podido subir algún fichero.']);
        $this->subidas = array_merge($this->subidas, $this->nuevas);
        $this->nuevas = [];
    }

    /** Quita un fichero aún no guardado de la lista del modal. */
    public function quitarSubida(int $i): void
    {
        unset($this->subidas[$i]);
        $this->subidas = array_values($this->subidas);
    }

    /** Borra un adjunto ya guardado (y su fichero). */
    public function quitarAdjunto(int $id): void
    {
        abort_unless(auth()->user()?->can('entidades.editar'), 403);
        $a = PuAdjunto::find($id);
        if ($a && ModelPu::whereKey($a->pu_id)->where('entidad_id', $this->entidad->id)->exists()) {
            Storage::disk('local')->delete($a->ruta);
            $a->delete();
        }
    }

    public function delete($puId)
    {
        abort_unless(auth()->user()?->can('entidades.editar'), 403);
        $pu = ModelPu::find($puId);
        if ($pu) {
            foreach ($pu->adjuntos as $a) {
                Storage::disk('local')->delete($a->ruta);
            }
            $pu->delete();
            // session()->flash('message', $entidad->entidad.' eliminado!');
            $this->dispatch('notify', 'La Pu ha sido eliminada!');
        }
    }

}
