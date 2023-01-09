<?php

namespace App\Http\Controllers;

use App\Actions\PlanFacturacionAction;
use App\Models\Entidad;
use App\Models\FacturacionConcepto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class EntidadController extends Controller
{

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('entidad.create');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Entidad $entidad){
        $ruta=Route::currentRouteName();
        return view('entidad.edit',compact('entidad','ruta'));
    }


    public function pus(Entidad $entidad)
    {
        return view('entidad.pus',compact('entidad'));
    }

    // public function facturacionconceptos(Entidad $entidad)
    // {
    //     return view('entidad.facturacionconceptos',compact('entidad'));
    // }

    public function planfacturacion(Entidad $entidad)
    {
        $conceptos=FacturacionConcepto::where('entidad_id',$entidad->id)->get();
        foreach ($conceptos as $concepto){
            $p= new PlanFacturacionAction;
            $p->execute($entidad,$concepto);
        }

        return view('entidad.facturacionconceptos',compact('entidad'));
    }

    public function contactos(Entidad $entidad)
    {
        return view('entidad.contactos',compact('entidad'));
    }

    public function createcontacto($contactoId)
    {
        $contacto=Entidad::find($contactoId);
        return view('entidad.createcontacto',compact('contacto'));
    }



}
