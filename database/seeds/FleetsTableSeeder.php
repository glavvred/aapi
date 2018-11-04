<?php

use Carbon\Carbon;
use Faker\Factory as Faker;
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

//        $planets = App\Planet::with('ships')->inRandomOrder()->take(1000);
//
//        $planets->each(function ($p) {
//            $faker = Faker::create();
//            if (!empty($p->owner_id)) { //поднимаем флоты только с планет
//                $owner = $p->owner()->first();
//
//                foreach ($p->ships as $ship) {
//                    $fleet[] = [$ship->pivot->ship_id, $ship->pivot->quantity];
//                }
//
//                echo ' pid: ' . $p->id . ', ';
//                echo ' owner: ' . $owner->id . ', ';
//                echo ' word: ' . $faker->word . ', ';
//                echo "\r\n";
//
//                if (!empty($fleet)) {
//                    DB::table('fleets')->insert([
//                        'owner_id' => $owner->id,
//                        'name' => $faker->unique()->word,
//                        'ships' => json_encode($fleet),
//                        'coordinate_id' => $p->id,
//                        'orderType' => null,
//                        'captainId' => null,
//                        'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
//                        'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
//                    ]);
//                }
//            }
//
//        });
    }
}

