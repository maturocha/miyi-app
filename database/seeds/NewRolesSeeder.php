<?php

use Illuminate\Database\Seeder;
use App\Models\Role;

class NewRolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //

        $roles = [
            ['name' => 'Administración'],
            ['name' => 'Farmacia']
        ];
    
        Role::insert($roles);
    }
}
