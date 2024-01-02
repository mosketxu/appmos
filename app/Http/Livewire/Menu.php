<?php

namespace App\Http\Livewire;

use App\Models\Entidad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Livewire\Component;

class Menu extends Component
{

    public $entmenu;
    public $ruta;
    public $filtroentidad;


    public function mount(Entidad $entidad,$ruta){
        $this->entmenu=$entidad;
        if($ruta!='') $this->ruta=$ruta;
    }


    public function render()
    {
        $entidades=Entidad::orderBy('entidad')->get();
        return view('livewire.menu',compact('entidades'));
    }


    public function updatedFiltroentidad(){
        $e=Entidad::find($this->filtroentidad);
        return redirect()->route($this->ruta,$e);
    }

}
