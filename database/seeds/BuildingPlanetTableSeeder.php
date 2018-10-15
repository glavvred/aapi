<?php

use Illuminate\Database\Seeder;

class BuildingPlanetTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $planets = App\Planet::with('owner')->get();
        $planets->each(function($p){
            $race = $p->owner()->first()->race;
            $building = DB::table('buildings')->where('race', $race)->get();
            $num = random_int(0,10);
            for ($i=1; $i < $num; $i++){
                echo 'num: '.$num .', ';
                echo 'pid: '.$p->id. ', ';
                echo 'bid: '.$building[$i]->id. ', ';

                $p->buildings()->attach($p->id,[
                    'building_id' => $building[$i]->id,
                    'level' => random_int(1,20),
                    'destroying' => 0]);
            }
        });
    }
}
