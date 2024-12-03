<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Chạy các migration.
     */
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('images');
            $table->text('image')->nullable();
        });
    }

    /**
     * Đảo ngược các migration.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('image');
            $table->text('images')->nullable();
        });
    }
};
