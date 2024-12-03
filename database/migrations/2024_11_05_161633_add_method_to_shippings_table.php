<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMethodToShippingsTable extends Migration
{
    public function up()
    {
        Schema::table('shippings', function (Blueprint $table) {
            $table->string('method')->after('shipping_amount');
        });
    }

    public function down()
    {
        Schema::table('shippings', function (Blueprint $table) {
            $table->dropColumn('method');
        });
    }
}
