<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateFleetShipsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fleet_ships', function (Blueprint $table) {
            $table->increments('id')->unsigned()->unique();

            $table->integer('fleet_id')->unsigned();
            $table->foreign('fleet_id')->references('id')
                ->on('fleets')->onDelete('cascade');

            $table->integer('ship_id')->unsigned();
            $table->foreign('ship_id')->references('id')
                ->on('ships')->onDelete('cascade');

            $table->unique(['fleet_id', 'ship_id'], 'fleet_id_ship_id_unique');

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
        Schema::dropIfExists('fleet_ships');
    }
}
