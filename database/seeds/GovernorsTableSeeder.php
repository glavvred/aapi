<?php

use Illuminate\Database\Seeder;

class GovernorsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        factory(App\Governor::class, 30)->create();
    }
}
