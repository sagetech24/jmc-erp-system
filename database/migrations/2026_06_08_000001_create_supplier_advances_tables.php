<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 4);
            $table->decimal('amount_applied', 15, 4)->default(0);
            $table->string('payment_method', 32);
            $table->string('status', 32);
            $table->dateTime('paid_at');
            $table->dateTime('cleared_at')->nullable();
            $table->string('cheque_number', 64)->nullable();
            $table->date('cheque_date')->nullable();
            $table->string('cheque_bank')->nullable();
            $table->string('cheque_payee')->nullable();
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'purchase_order_id']);
            $table->index(['tenant_id', 'supplier_id', 'status']);
            $table->index(['tenant_id', 'status', 'cheque_date']);
        });

        Schema::create('supplier_advance_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_advance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('accounts_payable_id')->constrained('accounts_payable')->restrictOnDelete();
            $table->decimal('amount', 15, 4);
            $table->dateTime('applied_at');
            $table->timestamps();

            $table->index('accounts_payable_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_advance_applications');
        Schema::dropIfExists('supplier_advances');
    }
};
