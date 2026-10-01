<?php

namespace App\Http\Livewire;

use App\Models\Entidad;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Ents extends Component
{
    use WithPagination;

    // En la URL: al entrar a editar y volver atrás se siguen aplicando (pedido 1-oct-2026)
    #[Url(except: '')]
    public $search='';
    #[Url(except: '')]
    public $filtrocliente='';
    #[Url(except: '')]
    public $filtroactivo='';
    #[Url(except: '')]
    public $filtrofacturar='';
    public Entidad $entidad;
    public $ruta;

    /** Clic en Cliente / Proveedor / Contacto del listado: cambia y se guarda al momento (independientes entre sí). */
    public function alternar(int $entidadId, string $campo)
    {
        if (! in_array($campo, ['cliente', 'proveedor', 'contacto'], true) || ! auth()->user()->can('entidades.editar')) {
            return;
        }
        $entidad = Entidad::find($entidadId);
        if ($entidad) {
            $entidad->{$campo} = ! $entidad->{$campo};
            $entidad->save();
        }
    }

    /** Al cambiar la búsqueda o un filtro, a la primera página. */
    public function updated($propiedad)
    {
        if (in_array($propiedad, ['search', 'filtrocliente', 'filtroactivo', 'filtrofacturar'])) {
            $this->resetPage();
        }
    }

    public function render()
    {
        $this->entidad= new Entidad;
        $this->ruta='entidades';
        $entidades=Entidad::query()
            ->with('entidadtipo')
            ->with('cicloimp')
            ->with('ciclofac')
            ->when($this->filtrocliente!='', function ($query){
                $query->where('cliente',$this->filtrocliente);
                })
            ->when($this->filtroactivo!='', function ($query){
                $query->where('estado',$this->filtroactivo);
                })
            ->when($this->filtrofacturar!='', function ($query){
                $query->where('facturar',$this->filtrofacturar);
                })
            // Entre paréntesis, para que el OR del NIF no se salte los filtros
            ->where(fn ($q) => $q->search('entidad',$this->search)->orSearch('nif',$this->search))
            ->orderBy('favorito','desc')
            ->orderBy('entidad','asc')
            ->paginate(15);

        return view('livewire.ents',compact('entidades'));
    }

    public function delete($entidadId)
    {
        $entidad = Entidad::find($entidadId);
        if ($entidad) {
            $entidad->delete();
            // session()->flash('message', $entidad->entidad.' eliminado!');
            $this->dispatch('notify', 'La entidad: '.$entidad->entidad.' ha sido eliminada!');
        }
    }
}
