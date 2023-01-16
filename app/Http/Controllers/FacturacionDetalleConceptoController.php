<?php

namespace App\Http\Controllers;

use App\Models\Facturacion;
use App\Models\FacturacionConceptodetalle;
use App\Models\FacturacionDetalle;
use App\Models\FacturacionDetalleConcepto;
use Illuminate\Http\Request;

class FacturacionDetalleConceptoController extends Controller
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
     * @param  \App\Http\Requests\StoreFacturacionDetalleConceptoRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store($request)
    {
        dd($request);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\FacturacionDetalleConcepto  $facturacionDetalleConcepto
     * @return \Illuminate\Http\Response
     */
    public function show(FacturacionDetalleConcepto $facturacionDetalleConcepto)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\FacturacionDetalleConcepto  $facturacionDetalleConcepto
     * @return \Illuminate\Http\Response
     */
    public function edit(FacturacionDetalleConcepto $facturacionDetalleConcepto)
    {
        //
    }

    public function update( Request $request, $id){
        $fdc=FacturacionDetalleConcepto::find($id);
        $fdc->orden=$request->orden;
        $fdc->tipo=$request->tipo;
        $fdc->subcuenta=$request->subcuenta;
        $fdc->concepto=$request->concepto;
        $fdc->unidades=$request->unidades;
        $fdc->importe=$request->importe;
        $fdc->iva=$fdc->tipo=='1'? '0.00' : $request->iva;
        $fdc->save();
        $fdc->calculo();

        $fd=FacturacionDetalle::find($fdc->facturaciondetalle_id);
        $f=Facturacion::find($fd->facturacion_id);

        if(!is_null($f->numfactura) || $f->numfactura!='' ) $f->pdffactura($f);

        $notification = array(
            'message' => 'Elemento actualizado satisfactoriamente!',
            'alert-type' => 'success'
        );

        return redirect()->back();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\FacturacionDetalleConcepto  $facturacionDetalleConcepto
     * @return \Illuminate\Http\Response
     */
    public function destroy(FacturacionDetalleConcepto $facturacionDetalleConcepto)
    {
        //
    }
}
