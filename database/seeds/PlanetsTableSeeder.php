<?php

use Illuminate\Database\Seeder;

class PlanetsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
//        // create 30000 planets using the user factory
        factory(App\Planet::class, 30000)->create();


    }
}
