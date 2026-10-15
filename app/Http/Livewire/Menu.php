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
    public $iconos = true;   // false en las pantallas de la entidad: los iconos van en su cabecera (x-buscador-entidad)


    public function mount(Entidad $entidad,$ruta,$iconos=true){
        $this->entmenu=$entidad;
        $this->iconos=$iconos;
        if($ruta!='') $this->ruta=$ruta;
    }


    public function render()
    {
        return view('livewire.menu');
    }

}
