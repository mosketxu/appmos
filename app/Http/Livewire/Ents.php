<?php

namespace App\Http\Livewire;

use App\Models\Entidad;
use App\Models\Suma;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Ents extends Component
{
    use WithPagination;
    use Concerns\CoResponsables;

    // En la URL: al entrar a editar y volver atrás se siguen aplicando (pedido 1-oct-2026)
    #[Url(except: '')]
    public $search='';
    #[Url(except: '')]
    public $filtrocliente='';
    #[Url(except: '')]
    public $filtroactivo='';
    #[Url(except: '')]
    public $filtrofacturar='';
    /** '' = todos, '0' = sin responsable, id de sumas = ese responsable. */
    #[Url(except: '')]
    public $filtroresponsable='';
    public Entidad $entidad;
    public $ruta;

    /** Clic en Cliente / Proveedor / Contacto / Facturar / Estado del listado: cambia y se guarda al momento. */
    public function alternar(int $entidadId, string $campo)
    {
        if (! in_array($campo, ['cliente', 'proveedor', 'contacto', 'facturar', 'estado'], true) || ! auth()->user()->can('entidades.editar')) {
            return;
        }
        $entidad = Entidad::find($entidadId);
        if ($entidad) {
            $entidad->{$campo} = $campo === 'estado' ? (Entidad::ESTADO_SIGUIENTE[(int) $entidad->estado] ?? 1) : ! $entidad->{$campo};
            $entidad->save();
        }
    }

    /** Responsable Suma desde el listado (se guarda al momento). */
    public function cambiarResponsable(int $entidadId, $sumaId)
    {
        if (! auth()->user()->can('entidades.editar') || ! ($entidad = Entidad::find($entidadId))) {
            return;
        }
        $entidad->suma_id = $sumaId !== '' && Suma::whereKey((int) $sumaId)->exists() ? (int) $sumaId : null;
        $entidad->save();
        \App\Support\Accesos::olvidar();
    }

    /** Clic en el ciclo de impuestos: pasa al siguiente (mismo orden que Proc.Mensuales) y se guarda. */
    public function siguienteCiclo(int $entidadId)
    {
        if (! auth()->user()->can('entidades.editar') || ! ($entidad = Entidad::find($entidadId))) {
            return;
        }
        $orden = \App\Http\Livewire\Contabilidad\ProcesosMensuales::ORDEN_CICLOS;
        $i = array_search((int) $entidad->cicloimpuesto_id, $orden, true);
        $entidad->cicloimpuesto_id = $orden[$i === false ? 0 : ($i + 1) % count($orden)];
        $entidad->save();
    }

    /** Al cambiar la búsqueda o un filtro, a la primera página. */
    public function updated($propiedad)
    {
        if (in_array($propiedad, ['search', 'filtrocliente', 'filtroactivo', 'filtrofacturar', 'filtroresponsable'])) {
            $this->resetPage();
        }
    }

    public function render()
    {
        $this->entidad= new Entidad;
        $this->ruta='entidades';
        $entidades=Entidad::query()
            ->with('entidadtipo')
            ->with('ciclofac')
            ->when($this->filtrocliente!='', function ($query){
                $query->where('cliente',$this->filtrocliente);
                })
            ->when($this->filtroactivo!='', function ($query){
                $query->where('estado',$this->filtroactivo);
                })
            ->when($this->filtrofacturar!='', function ($query){
                $query->where('facturar',$this->filtrofacturar);
                })
            // Por responsable principal o co-responsable (entidad_user del usuario de ese responsable)
            ->when((string) $this->filtroresponsable!=='', function ($query){
                if ((string) $this->filtroresponsable==='0') {
                    $query->whereNull('suma_id');
                } else {
                    $uid = Suma::whereKey($this->filtroresponsable)->value('user_id');
                    $query->where(fn ($q) => $q->where('suma_id',$this->filtroresponsable)
                        ->when($uid, fn ($q) => $q->orWhereIn('id', \Illuminate\Support\Facades\DB::table('entidad_user')->where('user_id',$uid)->select('entidad_id'))));
                }
                })
            // Entre paréntesis, para que el OR del NIF no se salte los filtros
            ->where(fn ($q) => $q->search('entidad',$this->search)->orSearch('nif',$this->search))
            ->orderBy('favorito','desc')
            ->orderBy('entidad','asc')
            ->paginate(15);

        $nombresCiclo = \Illuminate\Support\Facades\DB::table('ciclos')->pluck('ciclo', 'id')->map(fn ($c, $id) => $id === 0 ? 'Sin definir' : $c)->all();

        $sumas = Suma::orderBy('nombre')->get(['id', 'nombre', 'user_id']);
        $coResp = self::coResponsablesDe(array_merge($entidades->pluck('id')->all(), array_filter([$this->coRespDe])));

        return view('livewire.ents',compact('entidades', 'nombresCiclo', 'sumas', 'coResp'));
    }

    public function delete($entidadId)
    {
        $entidad = Entidad::find($entidadId);
        if ($entidad) {
            $entidad->delete();
            // session()->flash('message', $entidad->entidad.' eliminado!');
            $this->dispatch('notify', 'La entidad: '.$entidad->entidad.' ha sido eliminada!');
        }
    }
}
