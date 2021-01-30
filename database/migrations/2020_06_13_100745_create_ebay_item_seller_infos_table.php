<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEbayItemSellerInfosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ebay_item_seller_infos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('itemId');
            $table->string('sellerUserName');
            $table->string('feedbackScore')->nullable();
            $table->string('positiveFeedbackPercent')->nullable();
            $table->string('feedbackRatingStar')->nullable();
            $table->string('topRatedSeller')->nullable();
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
        Schema::dropIfExists('ebay_item_seller_infos');
    }
}
