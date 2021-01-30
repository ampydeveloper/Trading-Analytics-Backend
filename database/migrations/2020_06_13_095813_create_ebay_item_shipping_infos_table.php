<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEbayItemShippingInfosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ebay_item_shipping_infos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('itemId');
            $table->string('shippingServiceCost')->nullable();
            $table->string('shippingType')->nullable();
            $table->string('shipToLocations')->nullable();
            $table->string('expeditedShipping')->nullable();
            $table->string('oneDayShippingAvailable')->nullable();
            $table->string('handlingTime')->nullable();
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
        Schema::dropIfExists('ebay_item_shipping_infos');
    }
}
