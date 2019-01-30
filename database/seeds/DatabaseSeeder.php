<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Model::unguard();
        // Register the user seeder
        $this->call([
            UsersTableSeeder::class,
            AllianceTableSeeder::class,
            PlanetsTableSeeder::class,
            WreckagesTableSeeder::class,
            CommentsTableSeeder::class,
            BuildingsTableSeeder::class,
            ShipsTableSeeder::class,
            BuildingPlanetTableSeeder::class,
            ShipPlanetTableSeeder::class,
            GovernorsTableSeeder::class,
            TechnologiesTableSeeder::class,
        ]);
        Model::reguard();
    }
}
