<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offer_redemption_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('offer_id');
            $table->unsignedBigInteger('order_id');
            $table->decimal('original_amount', 8, 2);
            $table->decimal('final_discount_given', 8, 2);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('offer_id')
                ->references('id')
                ->on('offers')
                ->restrictOnDelete();

            $table->foreign('order_id')
                ->references('id')
                ->on('orders')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_redemption_logs');
    }
};
