<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->index('sku', 'products_sku_index');
            $table->index('barcode', 'products_barcode_index');
            $table->index('category', 'products_category_index');
            $table->index('brand', 'products_brand_index');
            $table->index(['name', 'category'], 'products_name_category_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_sku_index');
            $table->dropIndex('products_barcode_index');
            $table->dropIndex('products_category_index');
            $table->dropIndex('products_brand_index');
            $table->dropIndex('products_name_category_index');
        });
    }
};
