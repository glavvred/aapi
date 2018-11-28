<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBuildingsLangTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('buildings_lang', function (Blueprint $table) {
            $table->increments('id')->unsigned();
            $table->string('building_name');
            $table->foreign('building_name')->references('name')
                ->on('buildings')->onDelete('cascade');

            $table->enum('language', ['russian', 'english']);
            $table->unique(['building_name', 'language']);
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
        Schema::dropIfExists('buildings_lang');
    }
}
