<?php

namespace App\Http\Livewire\Contabilidad;

use App\Models\Entidad;
use App\Support\Accesos;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Proc.Mensuales (1-oct-2026): agrupa varios procesos que se hacen cada mes.
 * Los procesos van por empresa: cada usuario ve solo las empresas que gestiona
 * (las del panel de control: Responsable Suma + asignadas, Accesos::entidadesPropias).
 * También Admin y gestores: aunque en Entidades vean todas, aquí solo las suyas.
 * Ver Contabilidad/ProcesosMensuales/PLAN.md.
 *
 * Pet. Documentación Impuestos: por empresa, el check entidades.mail_peticion_check
 * (de inicio todas marcadas; solo salen las marcadas salvo «ver también las no marcadas»),
 * el idioma (entidades.idioma: cambiarlo aquí lo cambia en la entidad) y el texto
 * entidades.mail_peticion, que parte de la plantilla de su idioma (tabla plantillas_mail,
 * ES / EN) y se personaliza. El check y el idioma se guardan al momento.
 *
 * Solo se ejecuta donde contabilidad.ejecucion_local está a true (PCs autorizados).
 */
class ProcesosMensuales extends Component
{
    /** [clave => ['icono', 'titulo', 'descripcion']]: una pestaña por proceso. */
    public const PROCESOS = [
        'petdocimpuestos' => ['icono' => '📨', 'titulo' => 'Pet. Documentación Impuestos',
            'descripcion' => 'Petición mensual de la documentación para los impuestos.'],
    ];

    public const IDIOMAS = ['ES' => 'Español', 'EN' => 'Inglés'];

    public string $proceso = 'petdocimpuestos';

    public string $buscar = '';

    /** Por empresa (id => valor), lo que se está editando en pantalla. */
    public array $textos = [];
    public array $checks = [];
    public array $idiomas = [];

    /** Plantillas del proceso (idioma => texto). */
    public array $plantillas = [];
    public bool $verPlantillas = false;

    /** Por defecto solo las marcadas. */
    public bool $verNoMarcadas = false;

    public function mount(): void
    {
        foreach ($this->empresas() as $e) {
            $this->textos[$e->id] = (string) $e->mail_peticion;
            $this->checks[$e->id] = (bool) $e->mail_peticion_check;
            $this->idiomas[$e->id] = $e->idioma === 'EN' ? 'EN' : 'ES';
        }
        $this->cargarPlantillas();
    }

    protected function empresas()
    {
        return Entidad::withoutGlobalScopes()
            ->whereIn('id', Accesos::entidadesPropias(auth()->user()) ?: [0])
            ->orderBy('entidad')->get(['id', 'entidad', 'alias', 'idioma', 'emailadm', 'emailgral', 'mail_peticion_check', 'mail_peticion']);
    }

    /** Solo se toca una empresa que gestiona el usuario. */
    protected function mia(int $id): bool
    {
        return in_array($id, Accesos::entidadesPropias(auth()->user()), true);
    }

    protected function cargarPlantillas(): void
    {
        $guardadas = DB::table('plantillas_mail')->where('proceso', $this->proceso)->pluck('texto', 'idioma')->all();
        foreach (array_keys(self::IDIOMAS) as $i) {
            $this->plantillas[$i] = (string) ($guardadas[$i] ?? '');
        }
    }

    public function guardarPlantillas(): void
    {
        foreach ($this->plantillas as $idioma => $texto) {
            if (isset(self::IDIOMAS[$idioma])) {
                DB::table('plantillas_mail')->updateOrInsert(
                    ['proceso' => $this->proceso, 'idioma' => $idioma],
                    ['texto' => $texto, 'updated_at' => now(), 'created_at' => now()]);
            }
        }
        $this->dispatch('proceso-terminado', mensaje: '✅ Plantillas guardadas');
    }

    protected function desdePlantilla(int $id, string $idioma): string
    {
        $nombre = Entidad::withoutGlobalScopes()->whereKey($id)->value('entidad');
        return str_replace('{empresa}', (string) $nombre, $this->plantillas[$idioma] ?? '');
    }

    /** Pone en el cuadro de la empresa la plantilla del idioma elegido (no guarda). */
    public function aplicarPlantilla(int $id): void
    {
        if ($this->mia($id)) {
            $this->textos[$id] = $this->desdePlantilla($id, $this->idiomas[$id] ?? 'ES');
        }
    }

    /** Rellena con la plantilla de su idioma las empresas marcadas que aún no tienen texto, y guarda. */
    public function rellenarVacias(): void
    {
        $n = 0;
        foreach ($this->checks as $id => $check) {
            if ($check && trim($this->textos[$id] ?? '') === '' && $this->mia((int) $id)) {
                $this->textos[$id] = $this->desdePlantilla((int) $id, $this->idiomas[$id] ?? 'ES');
                $this->guardar((int) $id, false);
                $n++;
            }
        }
        $this->dispatch('proceso-terminado', mensaje: $n ? "✅ {$n} empresas rellenadas con la plantilla" : 'No había empresas marcadas sin texto');
    }

    /** El check y el idioma se guardan en la entidad en cuanto se cambian. */
    public function updated(string $propiedad, $valor): void
    {
        if (! preg_match('/^(checks|idiomas)\.(\d+)$/', $propiedad, $m) || ! $this->mia((int) $m[2])) {
            return;
        }
        $e = Entidad::withoutGlobalScopes()->find((int) $m[2]);
        if ($m[1] === 'checks') {
            $e->mail_peticion_check = (bool) $valor;
        } elseif (isset(self::IDIOMAS[$valor])) {
            $e->idioma = $valor;
        }
        $e->save();
    }

    public function guardar(int $id, bool $avisar = true): void
    {
        if (! $this->mia($id)) {
            return;
        }
        $e = Entidad::withoutGlobalScopes()->find($id);
        $e->mail_peticion_check = (bool) ($this->checks[$id] ?? false);
        $e->mail_peticion = trim($this->textos[$id] ?? '') === '' ? null : $this->textos[$id];
        $e->save();
        if ($avisar) {
            $this->dispatch('proceso-terminado', mensaje: '✅ Guardado: '.$e->entidad);
        }
    }

    public function render()
    {
        $empresas = $this->empresas()
            ->when(! $this->verNoMarcadas, fn ($c) => $c->filter(fn ($e) => $this->checks[$e->id] ?? false))
            ->when($this->buscar !== '', fn ($c) => $c->filter(fn ($e) => stripos($e->entidad.' '.$e->alias, $this->buscar) !== false));

        return view('livewire.contabilidad.procesos-mensuales', [
            'procesos' => self::PROCESOS,
            'idiomasDisponibles' => self::IDIOMAS,
            'usuario' => auth()->user(),
            'empresas' => $empresas,
            'marcadas' => count(array_filter($this->checks)),
            'total' => count($this->checks),
        ]);
    }
}
