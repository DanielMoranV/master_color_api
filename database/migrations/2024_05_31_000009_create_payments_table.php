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
            $table->enum('payment_method', ['Efectivo', 'Tarjeta', 'Yape', 'Plin', 'TC', 'MercadoPago'])->default('MercadoPago');
            $table->string('payment_code')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('PEN');
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled', 'refunded'])->default('pending');
            $table->string('external_id')->nullable(); // MercadoPago payment ID
            $table->json('external_response')->nullable(); // Full MercadoPago response
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
