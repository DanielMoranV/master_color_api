<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_id')->constrained('stocks')->onDelete('cascade');
            $table->enum('movement_type', ['Entrada', 'Salida', 'Ajuste', 'Devolucion']);
            $table->integer('quantity');
            $table->string('reason');
            $table->decimal('unit_price', 10, 2);
            $table->foreignId('user_id')->constrained('users');
            $table->string('voucher_number')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('stock_movements');
    }
};
