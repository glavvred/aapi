<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUserTechnologiesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_technologies', function (Blueprint $table) {
            $table->integer('owner_id')->unsigned();
            $table->foreign('owner_id')->references('id')
                ->on('users')->onDelete('cascade');

            $table->integer('technology_id')->unsigned();
            $table->foreign('technology_id')->references('id')
                ->on('technologies')->onDelete('cascade');

            $table->integer('level')->unsigned();
            $table->integer('planet_id')->unsigned();
            $table->timestamp('startTime', 0)->nullable();
            $table->integer('timeToBuild')->nullable();
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
        Schema::dropIfExists('user_technologies');
    }
}
