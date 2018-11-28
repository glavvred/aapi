<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTechnologiesLangTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('technologies_lang', function (Blueprint $table) {
            $table->increments('id')->unsigned();
            $table->string('technology_name');
            $table->foreign('technology_name')->references('name')
                ->on('technologies')->onDelete('cascade');

            $table->enum('language', ['russian', 'english']);
            $table->unique(['technology_name', 'language']);
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
        Schema::dropIfExists('technologies_lang');
    }
}
