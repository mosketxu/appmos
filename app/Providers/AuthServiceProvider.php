<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        // Admin puede todo, tenga o no marcado cada permiso (ver config/accesos.php)
        Gate::before(fn ($user, $ability) => $user->hasRole('Admin') ? true : null);
        // Permisos de proceso (config/accesos.php 'procesos'): mientras no exista el permiso, vale el de su pestaña;
        // si existe, lo resuelve Spatie.
        Gate::after(function ($user, $ability, $result) {
            if (! is_string($ability) || ! str_starts_with($ability, 'proceso.') || ! ($padre = \App\Support\Accesos::padreDeProceso($ability))) {
                return null;
            }
            if (! \App\Support\Accesos::existe($ability)) {
                return $user->can($padre);
            }
            return null;   // si existe, manda Spatie (la pestaña ya la exige la ruta)
        });
    }
}
