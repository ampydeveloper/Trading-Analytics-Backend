<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEbayItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ebay_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('card_id');
            $table->string('itemId');
            $table->text('title');
            $table->string('globalId')->nullable();
            $table->unsignedBigInteger('category_id');
            $table->text('galleryURL')->nullable();
            $table->text('viewItemURL')->nullable();
            $table->string('paymentMethod')->nullable();
            $table->string('autoPay')->nullable();
            $table->string('postalCode')->nullable();
            $table->string('location')->nullable();
            $table->string('country')->nullable();
            $table->unsignedBigInteger('seller_info_id')->nullable();
            $table->unsignedBigInteger('shipping_info_id')->nullable();
            $table->unsignedBigInteger('selling_status_id')->nullable();
            $table->unsignedBigInteger('listing_info_id')->nullable();
            $table->string('returnsAccepted')->nullable();
            $table->unsignedBigInteger('condition_id')->nullable();
            $table->string('isMultiVariationListing')->nullable();
            $table->string('topRatedListing')->nullable();
            $table->timestamps();
            $table->softDeletes('deleted_at');
        });

        Schema::table('ebay_items', function (Blueprint $table) {
            $table->foreign('card_id')->references('id')->on('cards');
            $table->foreign('category_id')->references('id')->on('ebay_item_categories');
            $table->foreign('seller_info_id')->references('id')->on('ebay_item_seller_infos');
            $table->foreign('shipping_info_id')->references('id')->on('ebay_item_shipping_infos');
            $table->foreign('selling_status_id')->references('id')->on('ebay_item_selling_statuses');
            $table->foreign('listing_info_id')->references('id')->on('ebay_item_listing_infos');
            $table->foreign('condition_id')->references('id')->on('ebay_item_conditions');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ebay_items');
    }
}
