<?php

namespace App\Http\Controllers;

use App\Models\{Ciclo, FacturacionConcepto, Entidad, FacturacionConceptodetalle, FacturacionDetalle};
use Illuminate\Http\Request;

class FacturacionConceptoController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($entidad)
    {
        //
    }


    public function conceptosentidad(Entidad $entidad){
        $ciclosfact=Ciclo::get();
        $conceptos=FacturacionConcepto::where('entidad_id',$entidad->id)->with('detalles')->get();
        $ruta="facturacionconcepto.entidad";
        return view('facturacionconceptos.index',compact(['entidad','conceptos','ciclosfact','ruta']));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        dd('create');
        //
    }

    public function store(Request $request){
        $request->validate([
            'entidad_id'=>'required',
            'ciclo_id'=>'required',
            'concepto'=>'required',
            'importe'=>'nullable',
            'ciclocorrespondiente'=>'ciclocorrespondiente',
            ]);

        FacturacionConcepto::insert([
            'entidad_id'=>$request->entidad_id,
            'ciclo_id'=>$request->ciclo_id,
            'concepto'=>$request->concepto,
            'importe'=>$request->importe,
            'ciclocorrespondiente'=>$request->ciclocorrespondiente,
        ]);

        $notification = array(
            'message' => 'Elemento creado satisfactoriamente!',
            'alert-type' => 'success'
        );
        return redirect('store')->with($notification);
    }



    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        dd('lleg');
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
    public function update(Request $request, $id)
    {
        $f=FacturacionConcepto::find($id);
        $f->concepto=$request->agrup;
        $f->ciclo_id=$request->ciclo;
        $f->ciclocorrespondiente=$request->corresponde;
        $f->save();

        $notification = array(
            'message' => 'Elemento actualizado satisfactoriamente!',
            'alert-type' => 'success'
        );
        return redirect()->back()->with($notification);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id){
        FacturacionConcepto::where('id',$id)->delete();

        $notification = array(
            'message' => 'Concepto eliminado satisfactoriamente!',
            'alert-type' => 'success'
        );

        return redirect()->back()->with($notification);
    }
}
