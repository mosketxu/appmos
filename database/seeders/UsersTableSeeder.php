<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UsersTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {


        \DB::table('users')->delete();

        User::create(['name' => 'mosketxu','email' => 'mosketxu@gmail.com','password' => bcrypt('12345678'),
        ]);


    }
}
