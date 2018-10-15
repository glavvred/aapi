<?php

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
        'name'     => $faker->name,
        'email'    => $faker->unique()->email,
        'race'     => $faker->randomElement(['1', '2']),
        'password' => app('hash')->make('12345'),
    ];
});


//planets
$factory->define(App\Planet::class, function (Faker\Generator $faker) {
    $unique = $faker->unique()->regexify('[0-9][0-9][0-9][0-9][0-2][0-9]');
    $coordinateX = ltrim(substr($unique, 0, 2), '0');
    $coordinateY = ltrim(substr($unique, 2, 2), '0');
    $orbit = ltrim(substr($unique, 4, 2), '0');

    return [
        'owner_id'     => $faker->randomDigitNotNull,
        'name'         => $faker->streetName,
        'galaxy'         => 1,
        //юник по трем колонкам
        'coordinateX'  => $coordinateX,
        'coordinateY'  => $coordinateY,
        'orbit'        => $orbit,
        'type'         => $faker->randomElement(array('1', '2', '3', '4', '5')),
        'metal'        => $faker->randomNumber(4),
        'crystal'      => $faker->randomNumber(4),
        'gas'          => $faker->randomNumber(4),
    ];
});

//buildings
$factory->define(App\Building::class, function (Faker\Generator $faker) {
    return [
        'name'                  => $faker->colorName,
        'description'           => $faker->colorName,
        'cost_metal'            => $faker->randomNumber(2),
        'cost_crystal'          => $faker->randomNumber(2),
        'cost_gas'              => $faker->randomNumber(2),
        'cost_time'             => $faker->randomNumber(2),
        'metal_ph'              => $faker->randomNumber(2),
        'crystal_ph'            => $faker->randomNumber(2),
        'gas_ph'                => $faker->randomNumber(2),
        'energy_ph'             => $faker->randomElement(array('-1', '-1', '1')) * $faker->randomNumber(2),
        'dark_matter_cost'      => 0,
        'type'                  => $faker->randomElement(array('1', '2', '3', '4', '5')),
        'race'                  => $faker->randomElement(array('1', '2')),
    ];
});
