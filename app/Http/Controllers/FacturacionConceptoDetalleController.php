<?php

namespace App\Http\Controllers;

use App\Models\FacturacionConcepto;
use App\Models\FacturacionConceptodetalle;
use Illuminate\Http\Request;

use function PHPUnit\Framework\isNull;

class FacturacionConceptoDetalleController extends Controller
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
        $rules=[
            'facturacionconcepto_id'=>'required',
            'concepto'=>'required',
            'importe'=>'required|numeric',
            'orden'=>'nullable|numeric',
        ];

        $messages = [
            'facturacionconcepto_id'=>'Tiene que existir una agrupacion antes de añadir un concepto',
            'concepto'=>'Es necesario el concepto',
            'importe.numeric'=>'El importe debe ser numérico',
            'importe.required'=>'El importe es necesario',
            'orden.numeric'=>'El orden debe ser numérico',
        ];
        // dd($request);
        $detalle = FacturacionConceptodetalle::create([
            'facturacionconcepto_id'=>$request->facturacionconcepto_id,
            'concepto'=>$request->concepto,
            'unidades'=>$request->unidades,
            'orden'=>$request->orden=='' ? '0' : $request->orden  ,
            'importe'=>$request->importe,
        ]);

        $this->validate($request, $rules,$messages);

        $notification = array(
            'message' => 'Elemento añadido satisfactoriamente!',
            'alert-type' => 'success'
        );
        return redirect()->back()->with($notification);

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
        dd('edit');
        //
    }

    public function update(Request $request, $id){
        $rules=[
            'facturacionconcepto_id'=>'required',
            'concepto'=>'required',
            'importe'=>'required|numeric',
            'orden'=>'nullable|numeric',
        ];

        $messages = [
            'facturacionconcepto_id'=>'Tiene que existir una agrupacion antes de añadir un concepto',
            'concepto'=>'Es necesario el concepto',
            'importe.numeric'=>'El importe debe ser numérico',
            'importe.required'=>'El importe es necesario',
            'orden.numeric'=>'El orden debe ser numérico',
        ];

        $request->validate($rules,$messages);
        $f=FacturacionConceptodetalle::find($id);
        $f->orden=$request->orden;
        $f->concepto=$request->concepto;
        $f->unidades=$request->unidades;
        $f->importe=$request->importe;
        // dd($f->importe);
        $f->save();

        return redirect()->back()->with('success','Detalle actualizado satisfactoriamente');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        FacturacionConceptodetalle::where('id',$id)->delete();

        $notification = array(
           'message' => 'Detalle eliminado satisfactoriamente!',
           'alert-type' => 'success'
       );

       return redirect()->back()->with($notification);
    }
}
