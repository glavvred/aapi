<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateShipsLangTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ships_lang', function (Blueprint $table) {
            $table->increments('id')->unsigned();
            $table->string('ship_name');
            $table->foreign('ship_name')->references('name')
                ->on('ships')->onDelete('cascade');

            $table->enum('language', ['russian', 'english']);
            $table->unique(['ship_name', 'language']);
            $table->string('name');
            $table->string('description');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ships_lang');
    }
}
