<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateFleetsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fleets', function (Blueprint $table) {
            $table->string('name');
            $table->string('description');
            $table->tinyInteger('type');
            $table->tinyInteger('race');
            $table->integer('cost_metal');
            $table->integer('cost_crystal');
            $table->integer('cost_gas');
            $table->integer('cost_time');
//            $table->integer('metal_ph');
//            $table->integer('crystal_ph');
//            $table->integer('gas_ph');
            $table->integer('energy_ph');
            $table->integer('dark_matter_cost');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fleets');
    }
}
