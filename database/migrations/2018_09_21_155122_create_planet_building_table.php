<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePlanetBuildingTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('planet_building', function (Blueprint $table) {
//            $table->increments('id')->unsigned();
            $table->integer('planet_id')->unsigned();
            $table->foreign('planet_id')->references('id')
                ->on('planets')->onDelete('cascade');

            $table->integer('building_id')->unsigned();
            $table->foreign('building_id')->references('id')
                ->on('buildings')->onDelete('cascade');

            $table->integer('level')->unsigned();
            $table->timestamp('startTime', 0)->nullable();
            $table->integer('timeToBuild')->nullable();
            $table->boolean('destroying');
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('planet_building');
    }
}
