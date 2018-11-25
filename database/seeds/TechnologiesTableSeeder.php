<?php

use Illuminate\Database\Seeder;

class TechnologiesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        factory(App\Technology::class, 100)->create();

//        UPDATE `technologies` SET `resources` = '{\"cost\": {\"formula\": [{\"gas\": \"{$gas} * {$multiplier}*{$level}\", \"time\": \"({$metal} + {$crystal} + {$gas}) / 10\", \"level\": 0, \"metal\": \"{$metal} * {$multiplier}*{$level}\", \"energy\": \"{$energy} * {$multiplier}*{$level}\", \"crystal\": \"{$crystal}  *  {$multiplier}*{$level}\", \"dark_matter\": 1}, {\"gas\": \"{$gas} * {$multiplier}*{$level}\", \"level\": 20, \"metal\": \"{$metal} * {$multiplier}*{$level}\", \"energy\": \"{$energy} * {$multiplier}*{$level}\", \"crystal\": \"{$crystal} * {$multiplier}*{$level}\", \"dark_matter\": 1}], \"constant\": [{\"gas\": 3, \"time\": 10, \"level\": 0, \"metal\": 5, \"energy\": 7, \"crystal\": 10, \"multiplier\": 1.55, \"dark_matter\": 0}]}, \"storage\": {\"formula\": [{\"gas\": \"{$metal} * {$multiplier}*{$level}\", \"level\": 0, \"metal\": \"{$metal} * {$multiplier}*{$level}\", \"energy\": \"{$metal} * {$multiplier}*{$level}\", \"crystal\": \"{$metal} * {$multiplier}*{$level}\"}, {\"gas\": \"{$metal} * {$multiplier}*{$level}\", \"level\": 20, \"metal\": \"{$metal} * {$multiplier}*{$level}\", \"energy\": \"{$metal} * {$multiplier}*{$level}\", \"crystal\": \"{$metal} * {$multiplier}*{$level}\"}], \"constant\": [{\"gas\": 10, \"level\": 0, \"metal\": 10, \"crystal\": 12, \"multiplier\": 1.15}]}, \"production\": {\"formula\": [{\"gas\": \"{$metal} * {$multiplier}*{$level}\", \"level\": 0, \"metal\": \"{$metal} * {$multiplier}*{$level}\", \"energy\": \"{$metal} * {$multiplier}*{$level}\", \"crystal\": \"{$metal} * {$multiplier}*{$level}\"}, {\"gas\": \"{$metal} * {$multiplier}*{$level}\", \"level\": 20, \"metal\": \"{$metal} * {$multiplier}*{$level}\", \"energy\": \"{$metal} * {$multiplier}*{$level}\", \"crystal\": \"{$metal} * {$multiplier}*{$level}\"}], \"constant\": [{\"gas\": 1, \"level\": 0, \"metal\": 1, \"energy\": -1, \"crystal\": 2, \"multiplier\": 1.55}]}}', `requirements` = '{\"building\": {\"formula\": [{\"level\": 0}, {\"mine\": \"{$level} - 2\", \"level\": 3}, {\"mine\": \"{$level} - 2\", \"level\": 10, \"fusion\": \"1\"}, {\"mine\": \"{$level} - 2\", \"level\": 15, \"fusion\": \"10*{$multiplier}\", \"terraformer\": \"1\"}], \"constant\": [{\"level\": 0, \"multiplier\": 2}]}, \"technology\": {\"formula\": [{\"level\": 0}, {\"self\": \"{$level} - 1\", \"level\": 1, \"speed\": \"{$level} - 1\"}, {\"self\": \"{$level} - 1\", \"level\": 10, \"speed\": \"{$level} - 1\", \"location\": \"1\"}, {\"self\": \"{$level} - 1\", \"level\": 15, \"speed\": \"{$level}/{$multiplier}\", \"defence\": \"1\", \"location\": \"10\"}], \"constant\": [{\"level\": 0, \"multiplier\": 2}]}}', `upgrades` = '{\"planet\": {\"robotics\": {\"formula\": [{\"level\": 0, \"speed\": \"{$level} * {$multiplier}\"}, {\"level\": 20, \"speed\": \"({$level} + 1) * {$multiplier}\"}], \"constant\": [{\"level\": 0, \"multiplier\": \"1.1\"}]}, \"light_fighter_armor\": {\"formula\": [{\"level\": 0, \"light_fighter_armor\": \"{$level} * {$multiplier}\"}, {\"level\": 20, \"light_fighter_armor\": \"({$level} + 1) * {$multiplier}\"}], \"constant\": [{\"level\": 0, \"multiplier\": \"10\"}]}}}' WHERE 1;

        $technologies = \App\Technology::all();

        foreach ($technologies as $key => $technology) {
            echo $technology->id . "\r\n";

            DB::table('technologies_lang')->insert([
                'technology_name' => $technology->name,
                'language' => 'english',
                'race' => $technology->race,
                'name' => 'someName '.$key,
                'description' => 'some long description'
            ]);
            DB::table('technologies_lang')->insert([
                'technology_name' => $technology->name,
                'language' => 'russian',
                'race' => $technology->race,
                'name' => 'технология '.$key,
                'description' => 'описание технологии'
            ]);
        }
    }
}
