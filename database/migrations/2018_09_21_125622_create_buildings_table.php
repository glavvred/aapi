<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBuildingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('buildings', function (Blueprint $table) {
            $table->increments('id')->unsigned();
            $table->string('name');
            $table->text('description');
            $table->tinyInteger('type');
            $table->tinyInteger('race');
            $table->integer('cost_metal');
            $table->integer('cost_crystal');
            $table->integer('cost_gas');
            $table->integer('cost_time');
            $table->integer('dark_matter_cost');
            $table->integer('metal_ph');
            $table->integer('crystal_ph');
            $table->integer('gas_ph');
            $table->integer('energy_ph');
            $table->json('resources');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('buildings');
    }
}
