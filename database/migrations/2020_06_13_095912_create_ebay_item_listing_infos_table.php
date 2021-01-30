<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEbayItemListingInfosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ebay_item_listing_infos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('itemId');
            $table->string('bestOfferEnabled')->nullable();
            $table->string('buyItNowAvailable')->nullable();
            $table->string('startTime')->nullable();
            $table->string('endTime')->nullable();
            $table->string('listingType')->nullable();
            $table->string('gift')->nullable();
            $table->string('watchCount')->nullable();
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
        Schema::dropIfExists('ebay_item_listing_infos');
    }
}
