<?php

namespace App\Http\Livewire;

use App\Models\Entidad;
use Livewire\Component;
use Livewire\WithPagination;

class Ents extends Component
{
    use WithPagination;

    public $search='';
    public $filtrocliente='';
    public $filtroactivo='';
    public $filtrofacturar='';
    public Entidad $entidad;
    public $ruta;

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
            ->search('entidad',$this->search)
            ->orSearch('nif',$this->search)
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
            $this->dispatchBrowserEvent('notify', 'La entidad: '.$entidad->entidad.' ha sido eliminada!');
        }
    }
}
