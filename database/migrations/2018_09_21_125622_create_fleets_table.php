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
            $table->increments('id')->unsigned();

            $table->integer('owner_id')->unsigned();
            $table->foreign('owner_id')->references('id')
                ->on('users')->onDelete('cascade');

            $table->integer('origin_id')->unsigned();
            $table->foreign('origin_id')->references('id')
                ->on('planets')->onDelete('cascade');

            $table->integer('coordinate_id')->unsigned();
            $table->foreign('coordinate_id')->references('id')
                ->on('planets')->onDelete('cascade');

            $table->integer('destination_id')->unsigned()->nullable();
            $table->foreign('destination_id')->references('id')
                ->on('planets')->onDelete('cascade');

            $table->integer('captain_id')->unsigned()->nullable();
            $table->foreign('captain_id')->references('id')
                ->on('captains')->onDelete('cascade');

            $table->integer('order_type')->unsigned()->nullable();
            $table->foreign('order_type')->references('id')
                ->on('orders')->onDelete('cascade');


            $table->integer('overall_speed')->unsigned();
            $table->integer('overall_capacity')->unsigned();

            $table->integer('metal')->unsigned()->nullable();
            $table->integer('crystal')->unsigned()->nullable();
            $table->integer('gas')->unsigned()->nullable();

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
        Schema::dropIfExists('fleets');
    }
}
