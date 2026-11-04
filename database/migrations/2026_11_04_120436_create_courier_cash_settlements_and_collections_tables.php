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
        Schema::create('courier_cash_settlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('courier_id')->constrained('staff_users')->restrictOnDelete();
            $table->foreignId('reconciled_by_staff_user_id')->constrained('staff_users')->restrictOnDelete();
            $table->unsignedInteger('orders_count');
            $table->decimal('amount', 10, 2);
            $table->timestamp('settled_at');
            $table->timestamps();
        });

        Schema::create('courier_cash_collections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('courier_id')->constrained('staff_users')->restrictOnDelete();
            $table->foreignId('settlement_id')->nullable()->constrained('courier_cash_settlements')->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->timestamp('collected_at');
            $table->timestamps();
            $table->index(['courier_id', 'settlement_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courier_cash_collections');
        Schema::dropIfExists('courier_cash_settlements');
    }
};
