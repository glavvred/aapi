<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePlanetShipsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('planet_ships', function (Blueprint $table) {
            $table->integer('owner_id')->unsigned();
            $table->foreign('owner_id')->references('id')
                ->on('users')->onDelete('cascade');

            $table->integer('ship_id')->unsigned();
            $table->foreign('ship_id')->references('id')
                ->on('ships')->onDelete('cascade');

            $table->integer('fleet_id')->unsigned();
            $table->foreign('fleet_id')->references('id')
                ->on('fleets')->onDelete('cascade');

            $table->integer('coordinate_id')->unsigned();
            $table->foreign('coordinate_id')->references('id')
                ->on('planets')->onDelete('cascade');

            $table->integer('quantity')->unsigned();

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('planet_ships');
    }
}
