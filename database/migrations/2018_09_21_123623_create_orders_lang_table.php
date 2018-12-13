<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateOrdersLangTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('orders_lang', function (Blueprint $table) {
            $table->increments('id')->unsigned();
            $table->string('order_name');
            $table->foreign('order_name')->references('name')
                ->on('orders')->onDelete('cascade');

            $table->enum('language', ['russian', 'english']);
            $table->unique(['order_name', 'language']);

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
        Schema::dropIfExists('orders_lang');
    }
}
