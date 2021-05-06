<?php

use App\User;
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

        $user = new User;
        $user->type = 'user';
        $user->name = 'Usuario 1';
        $user->username = 'user1';
        $user->email = 'user1@santander.com.ar';
        $user->password = bcrypt('santander');
        $user->save();

        $user = new User;
        $user->type = 'user';
        $user->name = 'Usuario 2';
        $user->username = 'user2';
        $user->email = 'user2@santander.com.ar';
        $user->password = bcrypt('santander');
        
        $user->save();

        $user = new User;
        $user->type = 'user';
        $user->name = 'Usuario 3';
        $user->username = 'user3';
        $user->email = 'user2@santander.com.ar';
        $user->password = bcrypt('santander');
        
        $user->save();

        $user = new User;
        $user->type = 'user';
        $user->name = 'Usuario 4';
        $user->username = 'user4';
        $user->email = 'user2@santander.com.ar';
        $user->password = bcrypt('santander');
        
        $user->save();
    }
}
