<?php

use Illuminate\Database\Seeder;

class ShipsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        factory(App\Ship::class, 150)->create();

        $ships = \App\Ship::all();

        foreach ($ships as $key => $ship) {
            echo $ship->id . "\r\n";

            DB::table('ships_lang')->insert([
                'ship_name' => $ship->name,
                'language' => 'english',
                'race' => $ship->race,
                'name' => 'shipname ' . $key,
                'description' => 'some description'
            ]);
            DB::table('ships_lang')->insert([
                'ship_name' => $ship->name,
                'language' => 'russian',
                'race' => $ship->race,
                'name' => 'корабль ' . $key,
                'description' => 'описание корабля'
            ]);
        }

        //UPDATE `ships` SET `resources` = '{\"cost\": {\"formula\": [{\"gas\": \"{$gas} * {$multiplier}**{$level}\", \"time\": \"({$metal} + {$crystal} + {$gas}) / 10\", \"level\": 0, \"metal\": \"{$metal} * {$multiplier}**{$level}\", \"energy\": \"{$energy} * {$multiplier}**{$level}\", \"crystal\": \"{$crystal} * {$multiplier}**{$level}\", \"dark_matter\": 1}], \"constant\": [{\"gas\": 3, \"time\": 10, \"level\": 0, \"metal\": 5, \"energy\": 7, \"crystal\": 10, \"multiplier\": 1.55, \"dark_matter\": 0}]}, \"storage\": {\"formula\": [{\"gas\": \"{$metal} * {$multiplier}**{$level}\", \"level\": 0, \"metal\": \"{$metal} * {$multiplier}**{$level}\", \"energy\": \"{$metal} * {$multiplier}**{$level}\", \"crystal\": \"{$metal} * {$multiplier}**{$level}\"}], \"constant\": [{\"gas\": 10, \"level\": 0, \"metal\": 10, \"crystal\": 12, \"multiplier\": 1.15}]}, \"production\": {\"formula\": [{\"gas\": \"{$metal} * {$multiplier}**{$level}\", \"level\": 0, \"metal\": \"{$metal} * {$multiplier}**{$level}\", \"energy\": \"{$metal} * {$multiplier}**{$level}\", \"crystal\": \"{$metal} * {$multiplier}**{$level}\"}], \"constant\": [{\"gas\": 1, \"level\": 0, \"metal\": 1, \"energy\": -1, \"crystal\": 2, \"multiplier\": 1.55}]}}', `requirements` = '{\"building\": {\"formula\": [{\"mine\": 2, \"level\": 0, \"fusion\": \"10*{$multiplier}\", \"terraformer\": \"1\"}], \"constant\": [{\"level\": 0, \"multiplier\": 2}]}, \"technology\": {\"formula\": [{\"self\": 1, \"level\": 0, \"speed\": \"10/{$multiplier}\", \"defence\": \"1\", \"location\": \"10\"}], \"constant\": [{\"level\": 0, \"multiplier\": 2}]}}', `upgrades` = '{}', `properties` = '{\"combat\": {\"armor\": {\"formula\": [{\"armor\": \"{$light_fighter_armor} * {$multiplier}\", \"level\": 0}, {\"armor\": \"({$light_fighter_armor} + 1) * {$multiplier}\", \"level\": 20}], \"constant\": [{\"level\": 0, \"multiplier\": \"10\"}]}, \"attack\": {\"formula\": [{\"level\": 0, \"attack\": \"{$light_fighter_attack} * {$multiplier}\"}, {\"level\": 20, \"attack\": \"({$light_fighter_attack} + 1) * {$multiplier}\"}], \"constant\": [{\"level\": 0, \"multiplier\": \"1.1\"}]}, \"shield\": {\"formula\": [{\"level\": 0, \"shield\": \"{$light_fighter_shield} * {$multiplier}\"}, {\"level\": 20, \"shield\": \"({$level} + 1) * {$light_fighter_shield}\"}], \"constant\": [{\"level\": 0, \"multiplier\": \"1.1\"}]}}}' WHERE  1
    }
}
