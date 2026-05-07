<?php

use App\Models\User;
use Illuminate\Database\Seeder;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $user = new User;
        $user->type = 'superuser';
        $user->name = 'Matías Rocha';
        $user->username = 'admin';
        $user->email = 'admin@santander.com.ar';
        $user->password = bcrypt('santander');
        $user->save();
        
    }
}
