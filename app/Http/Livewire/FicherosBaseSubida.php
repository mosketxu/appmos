<?php

namespace App\Http\Livewire;

use App\Models\Entidad;
use App\Support\FicherosBase;
use Illuminate\Http\UploadedFile;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Subida de los ficheros base de SAGE (plan, clientes, proveedores, mayor, sumas y saldos) de una empresa desde CUALQUIER pantalla (19-oct-2026:
 * «no quiero salir del M111 para subir el listado de proveedores»). Se incrusta con
 * `@livewire('ficheros-base-subida', ['entidadId' => $id, 'tipos' => ['proveedores', 'mayor']], key('fb-'.$id))`.
 * Guarda en FicherosBase (central: lo ven todos los procesos; Facturas OCR lo instala al abrirse) y avisa con el evento `ficheros-base-cambiados`.
 */
class FicherosBaseSubida extends Component
{
    use WithFileUploads;

    public int $entidadId = 0;
    /** Tipos que se ofrecen (claves de FicherosBase::TIPOS); vacío = todos. */
    public array $tipos = [];
    public array $subidas = [];
    /** Mes (AAAA-MM) al que llega el sumas y saldos que se sube. */
    public string $periodoSys = '';
    public bool $abierto = false;
    public string $msg = '';
    public string $error = '';

    public function mount(int $entidadId = 0, array $tipos = [], bool $abierto = false): void
    {
        $this->entidadId = $entidadId;
        $this->tipos = array_values(array_filter($tipos ?: array_keys(FicherosBase::TIPOS), fn ($t) => isset(FicherosBase::TIPOS[$t])));
        $this->periodoSys = now()->subMonth()->format('Y-m');
        $this->abierto = $abierto;
    }

    protected function autorizar(): void
    {
        abort_unless($this->entidadId > 0 && Entidad::find($this->entidadId), 403);
    }

    public function updatedSubidas($valor, $clave): void
    {
        $this->autorizar();
        $tipo = (string) $clave;
        $f = $this->subidas[$tipo] ?? null;
        $this->subidas[$tipo] = null;
        $this->msg = $this->error = '';
        if (! in_array($tipo, $this->tipos, true) || ! $f instanceof UploadedFile) {
            return;
        }
        $nombre = $f->getClientOriginalName();
        if (! preg_match('/\.xlsx$/i', $nombre)) {
            $this->error = '«'.$nombre.'» tiene que ser un Excel (.xlsx) exportado de SAGE.';
            return;
        }
        $ruta = $f->getRealPath();
        $reglas = ['proveedores' => [['CifDni'], 'el listado de proveedores de SAGE (con CifDni y CodigoPostal)'],
            'clientes' => [['CifDni'], 'el listado de clientes de SAGE'],
            'plan' => [['Código cuenta', 'Descripción'], 'el plan de cuentas de SAGE (Código cuenta y Descripción)']];
        if (isset($reglas[$tipo]) && ! FicherosBase::contiene($ruta, ...$reglas[$tipo][0])) {
            $this->error = '«'.$nombre.'» no parece '.$reglas[$tipo][1].'. Súbelo igualmente desde la pantalla de Facturas OCR si estás seguro.';
            return;
        }
        if ($tipo === 'mayor' && ! FicherosBase::esMayorLineal($ruta)) {
            $this->error = '«'.$nombre.'» no es el mayor en columnas de SAGE (Código cuenta, Fecha asiento…).';
            return;
        }
        if ($tipo === 'sys' && (! FicherosBase::esSumasSaldos($ruta) || ! preg_match('/^\d{4}-\d{2}$/', $this->periodoSys))) {
            $this->error = '«'.$nombre.'» no es un balance de sumas y saldos de SAGE (o falta el mes al que llega).';
            return;
        }
        $r = FicherosBase::guardar($this->entidadId, $tipo, $ruta, $nombre, 'subido desde '.request()->header('Referer', 'una pantalla').' · '.auth()->user()->name,
            $tipo === 'sys' ? $this->periodoSys : null);
        if (! $r) {
            $this->error = 'No se ha podido guardar «'.$nombre.'».';
            return;
        }
        $this->msg = FicherosBase::TIPOS[$tipo]['titulo'].' guardado: «'.$nombre.'». Lo ven todos los procesos de esta empresa.';
        $this->dispatch('ficheros-base-cambiados', tipo: $tipo);
    }

    public function render()
    {
        $filas = [];
        if ($this->entidadId > 0) {
            foreach ($this->tipos as $t) {
                $filas[$t] = FicherosBase::TIPOS[$t] + ['ultimo' => FicherosBase::ultimo($this->entidadId, $t)];
            }
        }

        return view('livewire.ficheros-base-subida', ['filas' => $filas]);
    }
}
