<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFieldsToProductsTable extends Migration
{
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('product_type')->nullable(); // Loại sản phẩm
            $table->string('main_ingredient')->nullable(); // Thành phần chính
            $table->string('target_skin_type')->nullable(); // Đối tượng sử dụng
        });
    }

    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['product_type', 'main_ingredient', 'target_skin_type']);
        });
    }
}
