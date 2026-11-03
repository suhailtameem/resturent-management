<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupon_usages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coupon_config_id');
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('customer_phone', 20);
            $table->decimal('discount_amount_applied', 8, 2);
            $table->timestamp('created_at')->useCurrent();

            $table->index('customer_phone');

            $table->foreign('coupon_config_id')
                ->references('id')
                ->on('offer_coupon_configs')
                ->restrictOnDelete();

            $table->foreign('order_id')
                ->references('id')
                ->on('orders')
                ->restrictOnDelete();

            $table->foreign('customer_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_usages');
    }
};
