<?php

namespace App\Http\Livewire;

use App\Models\{Entidad,MetodoPago,Pais,Provincia,Suma, ContactoEntidad, };
use Livewire\Component;
use Illuminate\Validation\Rule;


class Ent extends Component
{
    public $entidad;
    public $contacto;
    public $contactoId;
    public $departamento;
    public $comentario;
    public $ruta="entidad.edit";

    public $showPlanModal=false;


    protected function rules()
    {
        return [
            'contactoId'=>'nullable',
            'departamento'=>'nullable',
            'comentario'=>'nullable',
            'contactoId'=>'nullable',
            'contacto.id'=>'nullable',
            'contacto.entidad'=>'nullable',
            'entidad.id'=>'nullable',
            'entidad.entidad'=>'required',
            'entidad.nif'=>'nullable|max:12',
            'entidad.direccion'=>'nullable',
            'entidad.codpostal'=>'max:10|nullable',
            'entidad.localidad'=>'nullable',
            'entidad.provincia_id'=>'nullable',
            'entidad.pais_id'=>'nullable',
            'entidad.tfno'=>'nullable',
            'entidad.emailgral'=>'nullable',
            'entidad.emailadm'=>'nullable',
            'entidad.web'=>'nullable',
            'entidad.cliente'=>'nullable',
            'entidad.cicloimpuesto_id'=>'nullable',
            'entidad.ciclofacturacion_id'=>'nullable',
            'entidad.metodopago_id'=>'nullable',
            'entidad.estado'=>'nullable',
            'entidad.facturar'=>'nullable',
            'entidad.enviar'=>'nullable',
            'entidad.idioma'=>'nullable',
            'entidad.banco1'=>'nullable',
            'entidad.banco2'=>'nullable',
            'entidad.banco3'=>'nullable',
            'entidad.iban1'=>'nullable',
            'entidad.iban2'=>'nullable',
            'entidad.iban3'=>'nullable',
            'entidad.diafactura'=>'numeric|nullable',
            'entidad.diavencimiento'=>'numeric|nullable',
            'entidad.referenciacliente'=>'nullable',
            'entidad.tipoiva'=>'numeric|required',
            'entidad.suma_id'=>'nullable',
            'entidad.porcentajemarta'=>'numeric|nullable',
            'entidad.porcentajesusana'=>'numeric|nullable',
            'entidad.favorito'=>'nullable',
            'entidad.observaciones'=>'nullable',
            'entidad.cuentacontable'=>'numeric|nullable',
        ];
    }

    public function mount(Entidad $entidad, Entidad $contacto,$ruta)
    {
        $this->entidad=$entidad;
        $this->contacto=$contacto;
        $this->ruta=$ruta;
    }

    public function render(){
        $entidad=$this->entidad;
        $contacto=$this->contacto;
        $this->contactoId=$contacto->id;

        $metodopagos=MetodoPago::all();
        $sumas=Suma::all();
        $provincias=Provincia::all();
        $paises=Pais::all();
        return view('livewire.ent',compact('metodopagos','sumas','provincias','paises'));
    }

    public function save()
    {
        if(!$this->entidad->facturar) $this->entidad->facturar=false;
        if(!$this->entidad->enviar) $this->entidad->enviar=false;
        if(!$this->entidad->iva) $this->entidad->iva='0.21';
        if($this->entidad->id){
            $i=$this->entidad->id;
            $this->validate([
                'entidad.entidad'=>[
                    'required',
                    Rule::unique('entidades','entidad')->ignore($this->entidad->id)],
                'entidad.nif'=>['nullable',
                    'max:12',
                    Rule::unique('entidades','nif')->ignore($this->entidad->id)],
                ]
            );
            $mensaje=$this->entidad->entidad . " actualizada satisfactoriamente";
        }else{
            $this->validate([
                'entidad.entidad'=>'required|unique:entidades,entidad',
                'entidad.nif'=>'max:12|unique:entidades,entidad',
                ]
            );
            $i=$this->entidad->id;
            $mensaje=$this->entidad->entidad . " creada satisfactoriamente";
        }

        $ent=Entidad::updateOrCreate([
            'id'=>$i
            ],
            [
            'entidad'=>$this->entidad->entidad,
            'nif'=>$this->entidad->nif,
            'direccion'=>$this->entidad->direccion,
            'codpostal'=>$this->entidad->codpostal,
            'localidad'=>$this->entidad->localidad,
            'provincia_id'=>$this->entidad->provincia_id,
            'pais_id'=>$this->entidad->pais_id,
            'tfno'=>$this->entidad->tfno,
            'emailgral'=>$this->entidad->emailgral,
            'emailadm'=>$this->entidad->emailadm,
            'web'=>$this->entidad->web,
            'cliente'=>$this->entidad->cliente,
            'cicloimpuesto_id'=>$this->entidad->cicloimpuesto_id,
            'ciclofacturacion_id'=>$this->entidad->ciclofacturacion_id,
            'metodopago_id'=>$this->entidad->metodopago_id,
            'estado'=>$this->entidad->estado,
            'facturar'=>$this->entidad->facturar,
            'enviar'=>$this->entidad->enviar,
            'idioma'=>$this->entidad->idioma,
            'banco1'=>$this->entidad->banco1,
            'banco2'=>$this->entidad->banco2,
            'banco3'=>$this->entidad->banco3,
            'iban1'=>$this->entidad->iban1,
            'iban2'=>$this->entidad->iban2,
            'iban3'=>$this->entidad->iban3,
            'diafactura'=>$this->entidad->diafactura,
            'diavencimiento'=>$this->entidad->diavencimiento,
            'referenciacliente'=>$this->entidad->referenciacliente,
            'tipoiva'=>$this->entidad->tipoiva,
            'suma_id'=>$this->entidad->suma_id,
            'porcentajemarta'=>$this->entidad->porcentajemarta,
            'porcentajesusana'=>$this->entidad->porcentajesusana,
            'favorito'=>$this->entidad->favorito,
            'observaciones'=>$this->entidad->observaciones,
            'cuentacontable'=>$this->entidad->cuentacontable,
            ]
        );
        if(!$this->entidad->id){
            $this->entidad->id=$ent->id;
        }

        if($this->contactoId){
            ContactoEntidad::create([
                 'contacto_id'=>$this->entidad->id,
                 'entidad_id'=>$this->contactoId,
                 'departamento'=>$this->departamento,
                 'comentarios'=>$this->comentario,
            ]);
            $this->dispatchBrowserEvent('notify', 'Contacto añadido con éxito');
        }
        $this->emitSelf('notify-saved');
    }


}
