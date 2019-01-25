<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUsersQuestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_quests', function (Blueprint $table) {
            $table->increments('id')->unsigned();

            $table->integer('user_id')->unsigned();
            $table->foreign('user_id')->references('id')
                ->on('users')->onDelete('cascade');

            $table->integer('quest_id')->unsigned();
            $table->foreign('quest_id')->references('id')
                ->on('quests')->onDelete('cascade');

            $table->boolean('claimed');

            $table->dateTime('started_at');
            $table->dateTime('completed_at');
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
        Schema::dropIfExists('user_quests');
    }
}
