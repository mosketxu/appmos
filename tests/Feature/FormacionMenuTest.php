<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** La pantalla Formación lleva el menú general de Appmos (se la había saltado). */
class FormacionMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_formacion_incluye_el_menu_general(): void
    {
        $id = DB::table('users')->insertGetId(['name' => 'Alex', 'email' => 'alex@example.com', 'password' => 'x', 'created_at' => now(), 'updated_at' => now()]);
        $u = User::find($id);
        Permission::findOrCreate('formacion.ver');
        $u->givePermissionTo('formacion.ver');

        \Livewire\Livewire::actingAs($u)->test(\App\Http\Livewire\Formacion::class)
            ->assertSee('Formación')->assertSee('Primary Navigation Menu', false);
    }
}
