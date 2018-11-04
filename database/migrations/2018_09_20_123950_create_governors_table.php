<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateGovernorsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('governors', function (Blueprint $table) {

            $table->increments('id')->unsigned();

            $table->string('name');
            $table->tinyInteger('type');
            $table->tinyInteger('race');
            $table->string('description');

            $table->integer('cost_metal');
            $table->integer('cost_crystal');
            $table->integer('cost_gas');
            $table->integer('energy_ph');
            $table->integer('dark_matter_cost');

            $table->integer('attack_bonus');
            $table->integer('defence_bonus');
            $table->integer('shield_bonus');
            $table->integer('speed_bonus');

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
        Schema::dropIfExists('governors');

    }
}
