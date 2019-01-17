<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePlanetDefenceTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('planet_defence', function (Blueprint $table) {
            $table->increments('id')->unsigned();

            $table->integer('planet_id')->unsigned();
            $table->foreign('planet_id')->references('id')
                ->on('planets')->onDelete('cascade');

            $table->integer('defence_id')->unsigned();
            $table->foreign('defence_id')->references('id')
                ->on('defences')->onDelete('cascade');

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
        Schema::dropIfExists('planet_defence');
    }
}
