<?php

namespace App\Http\Livewire\Admin;

use App\Models\Entidad;
use App\Models\Suma;
use App\Models\User;
use App\Support\Accesos;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Panel de control (solo Admin): usuarios, su rol, permisos extra, y a qué
 * entidades tiene acceso cada uno que no las vea todas. Una entidad la ve su
 * Responsable Suma (combo de Entidades, enlazado aquí con un usuario) y además
 * los usuarios que se le asignen a mano (tabla entidad_user).
 */
class Usuarios extends Component
{
    /** Usuario que se está editando (null = ninguno, 0 = nuevo). */
    public ?int $editando = null;

    public string $name = '';
    public string $email = '';
    public string $rol = 'Usuario';
    public bool $activo = true;
    public string $password = '';
    public ?string $sumaId = null;
    /** Permisos directos del usuario, además de los de su rol. */
    public array $permisosExtra = [];
    /** Entidades asignadas a mano. */
    public array $entidadesAsignadas = [];

    public string $buscarEntidad = '';
    public bool $soloMarcadas = false;
    public string $filtroUsuarios = '';

    public function nuevo(): void
    {
        $this->resetForm();
        $this->editando = 0;
    }

    public function editar(int $id): void
    {
        $u = User::with('roles', 'permissions', 'suma')->findOrFail($id);
        $this->resetForm();
        $this->editando = $u->id;
        $this->name = $u->name;
        $this->email = (string) $u->email;
        $this->rol = $u->getRoleNames()->first() ?? 'Usuario';
        $this->activo = (bool) $u->activo;
        $this->sumaId = $u->suma ? (string) $u->suma->id : null;
        $this->permisosExtra = $u->permissions->pluck('name')->all();
        $this->entidadesAsignadas = $u->entidadesAsignadas()->withoutGlobalScopes()->pluck('entidades.id')->map(fn ($i) => (string) $i)->all();
    }

    public function cancelar(): void
    {
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->reset(['editando', 'name', 'email', 'rol', 'activo', 'password', 'sumaId', 'permisosExtra', 'entidadesAsignadas', 'buscarEntidad', 'soloMarcadas']);
        $this->resetErrorBag();
    }

    public function guardar(): void
    {
        $id = $this->editando ?: null;
        $this->email = trim($this->email);
        $this->validate([
            'name' => 'required|string|max:255',
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'rol' => ['required', Rule::exists('roles', 'name')],
            'password' => ($id ? 'nullable' : 'nullable').'|string|min:8',
            'sumaId' => ['nullable', Rule::exists('sumas', 'id')],
        ], [
            'email.unique' => 'Ya hay otro usuario con ese correo.',
            'password.min' => 'La contraseña tiene que tener al menos 8 caracteres.',
        ]);

        $yo = auth()->user();
        if ($id === $yo->id && ($this->rol !== 'Admin' || ! $this->activo)) {
            $this->addError('rol', 'No puedes quitarte a ti mismo el rol Admin ni desactivarte.');
            return;
        }

        DB::transaction(function () use ($id) {
            $u = $id ? User::findOrFail($id) : new User();
            $u->name = trim($this->name);
            $u->email = $this->email !== '' ? $this->email : null;
            $u->activo = $this->activo;
            if ($this->password !== '') {
                $u->password = Hash::make($this->password);
            } elseif (! $u->exists) {
                $u->password = Hash::make(Str::random(40));
            }
            $u->save();

            $u->syncRoles([$this->rol]);
            $u->syncPermissions($this->permisosExtra);

            // Enlace con el Responsable Suma (uno por usuario)
            Suma::where('user_id', $u->id)->where('id', '!=', (int) $this->sumaId)->update(['user_id' => null]);
            if ($this->sumaId) {
                Suma::whereKey($this->sumaId)->update(['user_id' => $u->id]);
            }

            $u->entidadesAsignadas()->sync(array_map('intval', $this->entidadesAsignadas));
            $this->editando = $u->id;
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Accesos::olvidar();
        $this->password = '';
        $this->dispatch('proceso-terminado', mensaje: '✅ Usuario guardado: '.$this->name);
    }

    public function render()
    {
        $usuarios = User::with('roles', 'suma')
            ->when($this->filtroUsuarios !== '', fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$this->filtroUsuarios.'%')->orWhere('email', 'like', '%'.$this->filtroUsuarios.'%')))
            ->orderBy('name')->get();

        // Nº de entidades que ve cada usuario (null = todas)
        $vistas = [];
        foreach ($usuarios as $u) {
            $ids = Accesos::entidadesPermitidas($u);
            $vistas[$u->id] = $ids === null ? null : count($ids);
        }

        $entidades = collect();
        $porResponsable = [];
        if ($this->editando !== null) {
            $entidades = Entidad::withoutGlobalScopes()
                ->when($this->buscarEntidad !== '', fn ($q) => $q->where(fn ($q) => $q->where('entidad', 'like', '%'.$this->buscarEntidad.'%')->orWhere('alias', 'like', '%'.$this->buscarEntidad.'%')))
                ->when($this->soloMarcadas, fn ($q) => $q->whereIn('id', $this->entidadesAsignadas ?: [0]))
                ->orderBy('entidad')->limit(300)->get(['id', 'entidad', 'alias', 'estado', 'suma_id']);
            if ($this->sumaId) {
                $porResponsable = Entidad::withoutGlobalScopes()->where('suma_id', $this->sumaId)->orderBy('entidad')->pluck('entidad', 'id')->all();
            }
        }

        $rolElegido = Role::where('name', $this->rol)->first();

        return view('livewire.admin.usuarios', [
            'usuarios' => $usuarios,
            'vistas' => $vistas,
            'roles' => Role::orderBy('id')->pluck('name'),
            'sumas' => Suma::orderBy('nombre')->get(['id', 'nombre', 'user_id']),
            'gruposPermisos' => config('accesos.permisos'),
            'permisosDelRol' => $rolElegido ? $rolElegido->permissions->pluck('name')->all() : [],
            'entidades' => $entidades,
            'porResponsable' => $porResponsable,
        ]);
    }
}
