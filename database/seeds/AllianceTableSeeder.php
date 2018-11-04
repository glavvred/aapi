<?php

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;

class AllianceTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // create 2 alliances using the user factory
        factory(App\Alliance::class, 10)->create();
    }
}
