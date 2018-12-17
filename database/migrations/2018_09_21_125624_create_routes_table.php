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

            $table->integer('parent_id')->unsigned()->nullable();
            $table->foreign('parent_id')->references('id')
                ->on('routes')->onDelete('cascade');

            $table->integer('fleet_id')->unsigned();
            $table->foreign('fleet_id')->references('id')
                ->on('fleets')->onDelete('cascade');

            $table->integer('coordinate_id')->unsigned();
            $table->foreign('coordinate_id')->references('id')
                ->on('planets')->onDelete('cascade');

            $table->unique(['fleet_id', 'coordinate_id']);

            $table->integer('destination_id')->unsigned();
            $table->foreign('destination_id')->references('id')
                ->on('planets')->onDelete('cascade');

            $table->dateTime('start_time');

            $table->integer('order_id')->unsigned()->nullable();
            $table->foreign('order_id')->references('id')
                ->on('orders')->onDelete('cascade');

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
