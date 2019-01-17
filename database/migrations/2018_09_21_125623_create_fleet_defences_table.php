<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateFleetDefencesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fleet_defences', function (Blueprint $table) {
            $table->increments('id')->unsigned()->unique();

            $table->integer('fleet_id')->unsigned();
            $table->foreign('fleet_id')->references('id')
                ->on('fleets')->onDelete('cascade');

            $table->integer('defence_id')->unsigned();
            $table->foreign('defence_id')->references('id')
                ->on('defences')->onDelete('cascade');

            $table->unique(['fleet_id', 'defence_id'], 'fleet_id_defence_id_unique');

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
        Schema::dropIfExists('fleet_defences');
    }
}
