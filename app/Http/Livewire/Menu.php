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


    public function mount(Entidad $entidad,$ruta){
        $this->entmenu=$entidad;
        if($ruta!='') $this->ruta=$ruta;
    }


    public function render()
    {
        return view('livewire.menu');
    }

}
