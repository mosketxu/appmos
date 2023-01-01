<?php

namespace App\Http\Controllers;

use App\Models\Facturacion;
use App\Models\FacturacionDetalle;
use Illuminate\Http\Request;

class FacturacionDetalleController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }


    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id){
        $f=FacturacionDetalle::find($id);
        $f->concepto=$request->agrup;
        $f->orden=$request->orden;
        $f->save();

        // Facturacion::actualizaimportes($this->f->facturacion_id);


        $notification = array(
            'message' => 'Elemento actualizado satisfactoriamente!',
            'alert-type' => 'success'
        );
        return redirect()->back()->with($notification);    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id){
        $f=FacturacionDetalle::find($id);
        $f->delete();

        Facturacion::actualizaimportes($this->f->facturacion_id);

        $notification = array(
            'message' => 'Concepto eliminado satisfactoriamente!',
            'alert-type' => 'success'
        );

        return redirect()->back()->with($notification);
    }
}
