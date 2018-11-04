<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePlanetsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('planets', function (Blueprint $table) {
            $table->increments('id')->unsigned();
            $table->integer('owner_id')->unsigned()->nullable();
            $table->foreign('owner_id')->references('id')
                ->on('users')->onDelete('cascade');
            $table->string('name');
            $table->integer('slots');
            $table->integer('temperature');
            $table->integer('diameter');
            $table->integer('density');
            $table->integer('galaxy');
            $table->integer('coordinateX');
            $table->integer('coordinateY');
            $table->unique(['coordinateX', 'coordinateY', 'orbit']);
            $table->integer('orbit');
            $table->tinyInteger('type');
            $table->bigInteger('metal')->unsigned();
            $table->bigInteger('crystal')->unsigned();
            $table->bigInteger('gas')->unsigned();
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
        Schema::dropIfExists('planets');
    }
}
