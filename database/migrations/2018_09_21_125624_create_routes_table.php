<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateRoutesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('routes', function (Blueprint $table) {
            $table->increments('id')->unsigned();

            $table->integer('owner_id')->unsigned();
            $table->foreign('owner_id')->references('id')
                ->on('users')->onDelete('cascade');

            $table->integer('coordinate_id')->unsigned();
            $table->foreign('coordinate_id')->references('id')
                ->on('planets')->onDelete('cascade');

            $table->integer('route_id')->unsigned()->nullable();
            $table->foreign('route_id')->references('id')
                ->on('routes')->onDelete('cascade');

            $table->integer('captain_id')->unsigned()->nullable();
            $table->foreign('captain_id')->references('id')
                ->on('routes')->onDelete('cascade');

            $table->enum('order', ['transport, defence, transfer, colonization, attack'])->nullable();

            $table->integer('order_param')->unsigned()->nullable();

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
        Schema::dropIfExists('routes');
    }
}
