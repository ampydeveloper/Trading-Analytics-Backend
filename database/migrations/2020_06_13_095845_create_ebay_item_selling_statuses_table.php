<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEbayItemSellingStatusesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ebay_item_selling_statuses', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('itemId');
            $table->string('currentPrice')->nullable();
            $table->string('convertedCurrentPrice')->nullable();
            $table->string('sellingState')->nullable();
            $table->string('timeLeft')->nullable();
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
        Schema::dropIfExists('ebay_item_selling_statuses');
    }
}
