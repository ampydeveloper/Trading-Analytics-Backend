<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCardsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cards', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('player')->nullable();;
            $table->integer('year')->nullable();;
            $table->string('brand')->nullable();;
            $table->string('card')->nullable();;
            $table->enum('rc',['yes','no']);
            $table->string('variation')->nullable();;
            $table->string('grade')->nullable();
            $table->text('qualifiers')->nullable();;
            $table->timestamps();
            $table->softDeletes('deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cards');
    }
}
