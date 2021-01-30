<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAdvanceSearchOptionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('advance_search_options', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('type')->comment('Like team, season etc.');
            $table->string('keyword');
            $table->boolean('status')->default(1)->comment('0->disable, 1->active');
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
        Schema::dropIfExists('advance_search_options');
    }
}
