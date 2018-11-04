<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCaptainsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('captains', function (Blueprint $table) {
            $table->increments('id')->unsigned();
            $table->string('name');
            $table->string('description');
            $table->tinyInteger('type');
            $table->tinyInteger('race');
            $table->integer('cost_metal')->nullable();
            $table->integer('cost_crystal')->nullable();
            $table->integer('cost_gas')->nullable();
            $table->integer('dark_matter_cost')->nullable();
            $table->integer('attack_bonus')->nullable();
            $table->integer('defence_bonus')->nullable();
            $table->integer('shield_bonus')->nullable();
            $table->integer('speed_bonus')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('captains');
    }
}
