<?php

use Illuminate\Database\Seeder;

class ShipPlanetTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
//        $planets = App\Planet::with('owner');
//        $planets->each(function ($p) {
//            if (!empty($p->owner_id)) {
//                $race = $p->owner()->first()->race;
//                $shipsByRace = DB::table('ships')->where('race', $race)->get();
//                $num = random_int(0, 5); //типы кораблей
//                for ($i = 1; $i < $num; $i++) {
//                    echo 'num: ' . $num . ', ';
//                    echo 'pid: ' . $p->id . ', ';
//                    echo 'sid: ' . $shipsByRace[$i]->id . ', ';
//                    echo "\r\n";
//
//                    DB::table('planet_ships')->insert([
//                        'planet_id' => $p->id,
//                        'ship_id' => $shipsByRace[$i]->id,
//                        'quantity' => random_int(1, 2000),
//                    ]);
//                }
//            }
//        });
    }
}
