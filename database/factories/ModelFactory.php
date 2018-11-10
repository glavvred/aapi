<?php

use Carbon\Carbon;

/*
|--------------------------------------------------------------------------
| Model Factories
|--------------------------------------------------------------------------
|
| Here you may define all of your model factories. Model factories give
| you a convenient way to create models for testing and seeding your
| database. Just tell the factory how a default model should look.
|
*/

//users
$factory->define(App\User::class, function (Faker\Generator $faker) {
    return [
        'name' => $faker->name,
        'email' => $faker->unique()->email,
        'race' => $faker->randomElement(['1', '2']),
        'alliance_id' => $faker->randomElement(['1', '2']),
        'password' => app('hash')->make('12345'),
    ];
});

//alliances
$factory->define(App\Alliance::class, function (Faker\Generator $faker) {
    return [
        'name' => $faker->unique()->company,
        'type' => null,
        'description' => $faker->streetAddress,
        'parent_id' => null,
        'requirements' => 'TODO req list of tech|etc',
        'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
        'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
    ];
});

//wreckage
$factory->define(App\Wreckage::class, function (Faker\Generator $faker) {
    $planets = \App\Planet::all()->slice(10000, 10000);

    foreach ($planets as $planet) {
        echo $planet->id. "\r\n";

        DB::table('wreckages')->insert([
            'coordinate_id' => $planet->id,
            'metal' => $faker->randomNumber(4)*10,
            'crystal' => $faker->randomNumber(4)*10,
        ]);
    }

    return [
        'coordinate_id' => 1,
        'metal' => $faker->randomNumber(4)*10,
        'crystal' => $faker->randomNumber(4)*10,
    ];
});


//planets
$factory->define(App\Planet::class, function (Faker\Generator $faker) {
    $unique = $faker->unique()->regexify('[0-9][0-9][0-9][0-9][0-2][0-9]');
    $coordinateX = ltrim(substr($unique, 0, 2), '0');
    $coordinateY = ltrim(substr($unique, 2, 2), '0');
    $orbit = ltrim(substr($unique, 4, 2), '0');

    if (($orbit < 10) || ($orbit > 20)) {
        $owner = null;
    } else {
        $users = \App\User::all()->pluck('id')->toArray();
        if (rand(0,100) <= 10)
            $owner = $faker->randomElement($users);
        else
            $owner =  null;
    }
    $name = $faker->colorName;
    echo $orbit . ' ' . $name . "\r\n";

    return [
        'owner_id' => $owner,
        'name' => $name,
        'galaxy' => 1,
        //юник по трем колонкам
        'coordinateX' => $coordinateX,
        'coordinateY' => $coordinateY,
        'orbit' => $orbit,
        'slots' => rand(100, 300),
        'temperature' => rand(0, 300),
        'diameter' => rand(100, 3000) * 1000,
        'density' => rand(100, 1000),
        'type' => rand(1, 5),
        'metal' => $faker->randomNumber(4),
        'crystal' => $faker->randomNumber(4),
        'gas' => $faker->randomNumber(4),
    ];
});

//comments
$factory->define(App\Comments::class, function (Faker\Generator $faker) {

    $users = \App\User::all();
    $planets = \App\Planet::all()->shuffle()->slice(0, 100);

    foreach ($users as $user) {
        foreach ($planets as $planet) {
            echo $user->id . ' (' . $planet->coordinateX . '/' . $planet->coordinateY . ')' . "\r\n";

            DB::table('comments')->insert([
                'owner_id' => $user->id,
                'coordinateX' => $planet->coordinateX,
                'coordinateY' => $planet->coordinateY,
                'orbit' => $faker->randomElement([$planet->orbit, $planet->orbit, $planet->orbit, null]),
                'comment' => $faker->colorName,
                'description' => $faker->realText(),
                'share_with_alliance' => $faker->boolean(30),
                'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);
        }
    }

    return [
        'owner_id' => 1,
        'coordinateX' => 0,
        'coordinateY' => 0,
        'orbit' => null,
        'comment' => $faker->colorName,
        'description' => $faker->realText(),
        'share_with_alliance' => $faker->boolean(30),
        'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
        'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
    ];


});

//buildings
$factory->define(App\Building::class, function (Faker\Generator $faker) {
    return [
        'name' => $faker->colorName,
        'description' => $faker->text(1000),
        'cost_metal' => $faker->randomNumber(2),
        'cost_crystal' => $faker->randomNumber(2),
        'cost_gas' => $faker->randomNumber(2),
        'cost_time' => $faker->randomNumber(2),
        'metal_ph' => $faker->randomNumber(2),
        'crystal_ph' => $faker->randomNumber(2),
        'gas_ph' => $faker->randomNumber(2),
        'energy_ph' => $faker->randomElement(array('-1', '-1', '1')) * $faker->randomNumber(2),
        'dark_matter_cost' => 0,
        'type' => $faker->randomElement(array('1', '2', '3', '4', '5')),
        'race' => $faker->randomElement(array('1', '2')),
    ];
});

//ships
$factory->define(App\Ship::class, function (Faker\Generator $faker) {
    $name = $faker->colorName . ' ' . $faker->randomNumber(2);
    return [
        'name' => $name,
        'description' => $name . ' description long text',
        'cost_metal' => $faker->randomNumber(2),
        'cost_crystal' => $faker->randomNumber(2),
        'cost_gas' => $faker->randomNumber(2),
        'cost_time' => $faker->randomNumber(2),
//        'metal_ph'              => $faker->randomNumber(2),
//        'crystal_ph'            => $faker->randomNumber(2),
//        'gas_ph'                => $faker->randomNumber(2),
        'energy_ph' => $faker->randomElement(array('-1', '-1', '1')) * $faker->randomNumber(2),
        'dark_matter_cost' => 0,
        'type' => $faker->randomElement(array('1', '2', '3', '4', '5')),
        'race' => $faker->randomElement(array('1', '2')),
    ];
});

//governors
$factory->define(App\Governor::class, function (Faker\Generator $faker) {
    return [
        'name' => $faker->titleMale. ' '.$faker->userName,
        'description' => ' description long text',
        'cost_metal' => $faker->randomNumber(2),
        'cost_crystal' => $faker->randomNumber(2),
        'cost_gas' => $faker->randomNumber(2),
        'energy_ph' => $faker->randomElement(array('-1', '-1', '1')) * $faker->randomNumber(2),
        'dark_matter_cost' => 0,
        'type' => $faker->randomElement(array('1', '2', '3', '4', '5')),
        'race' => $faker->randomElement(array('1', '2')),
        'attack_bonus' => $faker->randomNumber(2),
        'defence_bonus' => $faker->randomNumber(2),
        'shield_bonus' => $faker->randomNumber(2),
        'speed_bonus' => $faker->randomNumber(2),
    ];
});
//technologies
$factory->define(App\Technology::class, function (Faker\Generator $faker) {
    return [
        'name' => 'technology: '.$faker->countryCode,
        'description' => ' description long text',

        'type' => $faker->randomElement(array('1', '2', '3', '4', '5')),
        'race' => $faker->randomElement(array('1', '2')),

        'cost_metal' => $faker->randomNumber(2),
        'cost_crystal' => $faker->randomNumber(2),
        'cost_gas' => $faker->randomNumber(2),
        'cost_time' => $faker->randomNumber(2),
        'dark_matter_cost' => 0,
    ];
});
