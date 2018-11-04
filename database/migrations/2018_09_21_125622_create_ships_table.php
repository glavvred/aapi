<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateShipsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ships', function (Blueprint $table) {
            $table->increments('id')->unsigned();
            $table->string('name');
            $table->string('description');
            $table->tinyInteger('type');
            $table->tinyInteger('race');
            $table->integer('cost_metal')->nullable();
            $table->integer('cost_crystal')->nullable();
            $table->integer('cost_gas')->nullable();
            $table->integer('cost_time');
            $table->integer('energy_ph')->nullable();
            $table->integer('dark_matter_cost')->nullable();
            $table->integer('attack')->nullable();
            $table->integer('defence')->nullable();
            $table->integer('shield')->nullable();
            $table->integer('speed')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ships');
    }
}
