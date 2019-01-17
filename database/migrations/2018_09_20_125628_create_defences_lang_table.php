<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDefencesLangTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('defences_lang', function (Blueprint $table) {
            $table->increments('id')->unsigned();
            $table->string('defences_name');
            $table->foreign('defences_name')->references('name')
                ->on('defences')->onDelete('cascade');

            $table->enum('language', ['russian', 'english']);
            $table->unique(['defences_name', 'language']);
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
        Schema::dropIfExists('defences_lang');
    }
}
