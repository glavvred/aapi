<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateWreckagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('wreckages', function (Blueprint $table) {
            $table->integer('coordinate_id')->unique()->unsigned();
            $table->foreign('coordinate_id')->references('id')
                ->on('planets')->onDelete('cascade');
            $table->bigInteger('metal')->unsigned();
            $table->bigInteger('crystal')->unsigned();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('wreckages');
    }
}
