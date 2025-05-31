<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->enum('payment_method', ['Efectivo', 'Tarjeta', 'Yape', 'Plin', 'TC']);
            $table->string('payment_code');
            $table->enum('document_type', ['Boleta', 'Factura', 'Ticket', 'NC'])->default('Ticket');
            $table->string('nc_reference')->nullable();
            $table->text('observations')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('payments');
    }
};
