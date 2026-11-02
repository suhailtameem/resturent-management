<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 30)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('customer_name', 100);
            $table->string('customer_phone', 20);
            $table->foreignId('delivery_address_id')->nullable()->constrained('customer_addresses')->nullOnDelete();
            $table->foreignId('delivery_zone_id')->nullable()->constrained('delivery_zones')->nullOnDelete();
            $table->foreignId('courier_id')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->string('order_status', 50)->default('pending_confirmation');
            $table->string('payment_method', 30)->nullable();
            $table->string('payment_status', 30)->default('pending');
            $table->decimal('subtotal_amount', 10, 2);
            $table->decimal('product_discount_amount', 10, 2)->default(0.00);
            $table->foreignId('coupon_id')->nullable()->constrained('coupons')->nullOnDelete();
            $table->decimal('coupon_discount_amount', 10, 2)->default(0.00);
            $table->decimal('delivery_fee', 8, 2)->default(0.00);
            $table->decimal('vat_tax_amount', 8, 2)->default(0.00);
            $table->decimal('total_final_amount', 10, 2);
            $table->text('chef_special_notes')->nullable();
            $table->unsignedInteger('estimated_prep_time_minutes')->default(30);
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
