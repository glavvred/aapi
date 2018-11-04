<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCoordinatesGovernorsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('coordinate_governors', function (Blueprint $table) {

            $table->integer('coordinate_id')->unsigned();
            $table->foreign('coordinate_id')->references('id')
                ->on('planets')->onDelete('cascade');

            $table->integer('governor_id')->unsigned();
            $table->foreign('governor_id')->references('id')
                ->on('governors')->onDelete('cascade');

            $table->integer('level');

            $table->integer('start_time');
            $table->integer('hired_for');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('coordinate_governors');

    }
}
