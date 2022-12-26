<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // \App\Models\User::factory(10)->create();
        // $this->call(CategoriasTableSeeder::class);
        // $this->call(CicloimpuestosTableSeeder::class);
        // $this->call(CiclosTableSeeder::class);
        // $this->call(MetodoPagosTableSeeder::class);
        // $this->call(PaisesTableSeeder::class);
        // $this->call(PeriodosTableSeeder::class);
        // $this->call(ProvinciasTableSeeder::class);
        // $this->call(SumasTableSeeder::class);
        // $this->call(UsersTableSeeder::class);
        $this->call(EntidadesSeeder::class);
        // $this->call(EmpresaTipoSeeder::class);
        // $this->call(FacturacionSeeder::class);
        // $this->call(FacturacionDetallesSeeder::class);

    }
}
