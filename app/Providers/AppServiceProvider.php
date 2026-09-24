<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // BuilComponent::macro('notify', function ($message) {
        //     $this->dispatchBrowserEvent('notify', $message);
        // });

        // Solo consulta: sin el permiso de escritura de config('accesos.escritura')
        // no se puede guardar ni borrar ese modelo, venga de la pantalla que venga.
        foreach (config('accesos.escritura', []) as $modelo => $permiso) {
            $comprobar = function () use ($permiso) {
                $user = auth()->user();
                if ($user && ! $user->can($permiso)) {
                    throw new \Illuminate\Auth\Access\AuthorizationException('Solo consulta: no tienes permiso para modificar estos datos.');
                }
            };
            $modelo::saving($comprobar);
            $modelo::deleting($comprobar);
        }

        Builder::macro('search', function ($field, $string) {
             return $string ? $this->where($field, 'like', '%'.$string.'%') : $this;
        });
        Builder::macro('orSearch', function ($field, $string) {
             return $string ? $this->orWhere($field, 'like', '%'.$string.'%') : $this;
        });
        Builder::macro('searchYear',function($field,$string){
            return $string ? $this->whereYear($field, 'like', '%'.$string.'%'): $this;
        });
        Builder::macro('searchMes',function($field,$string){
            return $string ? $this->whereMonth($field, $string): $this;
        });

        Builder::macro('toCsv', function () {
            $results = $this->get();
            if ($results->count() < 1) return;

            $sanitizeCell = function ($value) {
                if (is_string($value) && preg_match('/^[=+\-@\t\r]/', $value)) {
                    return "'".$value;
                }
                return $value;
            };

            $handle = fopen('php://output', 'w');

            fputcsv($handle, array_keys($results->first()->getAttributes()));
            foreach ($results as $result) {
                fputcsv($handle, array_map($sanitizeCell, $result->getAttributes()));
            }

            fclose($handle);
        });
    }
}
