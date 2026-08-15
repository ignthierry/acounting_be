<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->string('name');
            $table->string('sku')->nullable();
            $table->string('unit')->default('Pcs');
            $table->decimal('selling_price', 15, 2)->default(0.00);
            $table->decimal('cost_price', 15, 2)->default(0.00); // average cost
            $table->decimal('stock_quantity', 10, 2)->default(0.00);
            $table->decimal('min_stock_alert', 10, 2)->default(5.00);
            $table->timestamps();

            $table->unique(['company_id', 'sku']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
