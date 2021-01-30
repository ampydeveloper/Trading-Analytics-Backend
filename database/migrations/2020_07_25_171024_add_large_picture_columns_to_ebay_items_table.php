<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLargePictureColumnsToEbayItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('ebay_items', function (Blueprint $table) {
            $table->text('pictureURLLarge')->nullable()->after('topRatedListing');
            $table->text('pictureURLSuperSize')->nullable()->after('pictureURLLarge');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ebay_items', function (Blueprint $table) {
            //
        });
    }
}
