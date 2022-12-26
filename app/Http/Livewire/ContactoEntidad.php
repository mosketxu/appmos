<?php

namespace App\Http\Livewire;

use App\Models\ContactoEntidad as ModelContactoEntidad;
use App\Models\Entidad;
use Livewire\Component;
use Livewire\WithPagination;


class ContactoEntidad extends Component
{
    use WithPagination;

    public $entidad;
    public $contacto;
    public $departamento;
    public $comentario;
    public $search='';
    public $conts;
    public $ruta='entidad.contacto';


    // protected $rules = [
    //     'ent.id' => 'required',
    // ];

    public function render()
    {
        $ent=$this->entidad;
        $contactos = ModelContactoEntidad::where('entidad_id',$this->entidad->id)
        ->join('entidades','contacto_entidades.contacto_id','=','entidades.id')
        ->select('contacto_entidades.*', 'entidades.entidad', 'entidades.nif', 'entidades.tfno','entidades.emailgral')
        ->search('entidades.entidad',$this->search)
        ->orderBy('entidades.entidad')
        ->get();

        $excludedContactos = ModelContactoEntidad::where('entidad_id',$this->entidad->id)->pluck('contacto_id');
        $entidades=Entidad::whereNotIn('id',$excludedContactos)->orderBy('entidad')->get();

        return view('livewire.contacto-entidad',compact(['ent','contactos','entidades']));

    }

    public function savecontacto()
    {
        if($this->contacto){
            ModelContactoEntidad::create([
                'entidad_id'=>$this->entidad->id,
                'contacto_id'=>$this->contacto,
                'departamento'=>$this->departamento,
                'comentarios'=>$this->comentario,
                // ''
            ]);
            $this->dispatchBrowserEvent('notify', 'Contacto añadido con éxito');

            $this->reset('contacto');
            $this->reset('departamento');
            $this->reset('comentario');
        }
    }

    public function delete($contactoId)
    {
        $contactoBorrar = ModelContactoEntidad::find($contactoId);
        $e=Entidad::find($contactoBorrar->contacto_id);

        if ($contactoBorrar) {
            $contactoBorrar->delete();
            $this->dispatchBrowserEvent('notify', 'El contacto '.$e->entidad.'ha sido eliminado!');
        }
    }

}
