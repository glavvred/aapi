<?php

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //create two test users
        DB::table('users')->insert([
            'name' => 'test user 1',
            'email' => 'testuser1@gmail.com',
            'race' => 1,
            'alliance_id' => 1,
            'password' => app('hash')->make('12345'),
        ]);

        DB::table('users')->insert([
            'name' => 'test user 2',
            'email' => 'testuser2@gmail.com',
            'race' => 2,
            'alliance_id' => 2,
            'password' => app('hash')->make('12345'),
        ]);

        // create 30 users using the user factory
        factory(App\User::class, 28)->create();
    }
}
