<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateQuestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('quests', function (Blueprint $table) {
            $table->increments('id')->unsigned();
            $table->integer('id')->unsigned();
            $table->string('name');
            $table->tinyInteger('race');
            $table->unique(['name', 'race']);
            $table->boolean('is_hidden');

            $table->enum('type', ['storyline', 'daily', 'tutorial']);

            //requires
            // user stat (level, registration date, etc),
            // planet building level (current planet),
            // resources (current planet),
            // time of day
            $table->json('requirements');

            $table->json('reward_resources');
            $table->json('reward_items');
            $table->json('reward_units');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('quests');
    }
}
