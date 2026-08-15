<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('account_id')->constrained('accounts')->onDelete('restrict'); // Kas / Bank source
            $table->foreignId('contra_account_id')->nullable()->constrained('accounts')->onDelete('restrict'); // Beban / Pendapatan / Transfer destination
            $table->enum('type', ['in', 'out', 'transfer']);
            $table->decimal('amount', 15, 2);
            $table->date('transaction_date');
            $table->string('recipient_vendor')->nullable();
            $table->string('category')->nullable();
            $table->string('payment_method')->default('Cash');
            $table->text('notes')->nullable();
            $table->string('reference_number')->nullable();
            $table->enum('status', ['Lunas', 'Terjadwal'])->default('Lunas');
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->onDelete('set null');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_transactions');
    }
};
