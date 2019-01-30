<?php

use Illuminate\Database\Seeder;

class WreckagesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
//        // create 10000 wreckages using factory
        factory(App\Wreckage::class, 1)->create();
    }
}
