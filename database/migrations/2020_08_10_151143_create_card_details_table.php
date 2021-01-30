<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCardDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('card_details', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('card_id');
            $table->text('number')->nullable();
            $table->text('product')->nullable();
            $table->text('season')->nullable();
            $table->boolean('rookie')->default(false);
            $table->text('series')->nullable();
            $table->text('grade')->nullable();
            $table->text('manufacturer')->nullable();
            $table->text('era')->nullable();
            $table->text('year')->nullable();
            $table->text('grader')->nullable();
            $table->boolean('autographed')->default(false);
            $table->text('brand')->nullable();
            $table->timestamps();
            $table->softDeletes('deleted_at');
        });

        Schema::table('card_details', function (Blueprint $table) {
            $table->foreign('card_id')->references('id')->on('cards');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('card_details');
    }
}
