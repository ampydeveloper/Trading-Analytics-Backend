<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnsToCardsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->text('qualifiers2')->nullable()->after('qualifiers');
            $table->text('qualifiers3')->nullable()->after('qualifiers2');
            $table->text('qualifiers4')->nullable()->after('qualifiers3');
            $table->text('qualifiers5')->nullable()->after('qualifiers4');
            $table->text('qualifiers6')->nullable()->after('qualifiers5');
            $table->text('qualifiers7')->nullable()->after('qualifiers6');
            $table->text('qualifiers8')->nullable()->after('qualifiers7');
            $table->boolean('readyforcron')->default(0)->after('qualifiers8');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('cards', function (Blueprint $table) {
            //
        });
    }
}
