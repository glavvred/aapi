<?php

use Illuminate\Database\Seeder;

class FleetsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        factory(App\Fleet::class, 50)->create();

        $users = App\User::all();

        // Populate the pivot table
        App\User::all()->each(function ($user) use ($users) {
            $user->fleets()->attach(
                $users->random(rand(1, 3))->pluck('id')->toArray()
            );
        });
    }
}
