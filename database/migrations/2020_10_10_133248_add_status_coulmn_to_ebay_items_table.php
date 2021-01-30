<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStatusCoulmnToEbayItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('ebay_items', function (Blueprint $table) {
            $table->tinyInteger('status')->default(0)->comment('0->active,1->disabled by cron, 2-> disabled manually');
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
