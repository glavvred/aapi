<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePlanetShipTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('planet_ship', function (Blueprint $table) {
            $table->increments('id')->unsigned();

            $table->integer('planet_id')->unsigned();
            $table->foreign('planet_id')->references('id')
                ->on('planets')->onDelete('cascade');

            $table->integer('ship_id')->unsigned();
            $table->foreign('ship_id')->references('id')
                ->on('ships')->onDelete('cascade');

            $table->integer('quantity')->unsigned()->nullable();
            $table->integer('quantityQued')->unsigned()->nullable();

            $table->timestamp('startTime', 0)->nullable();
            $table->integer('timeToBuildOne')->unsigned()->nullable();
            $table->integer('passedFromLastOne')->unsigned()->nullable();
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
        Schema::dropIfExists('planet_ship');
    }
}
