<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->enum('type', ['coupon', 'auto_discount']);
            $table->enum('discount_scope', ['cart', 'specific_products', 'category']);
            $table->unsignedBigInteger('category_id')->nullable();
            $table->decimal('discount_percentage', 5, 2);
            $table->decimal('max_discount_cap', 8, 2);
            $table->decimal('min_order_amount', 8, 2)->default(0.00);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_ongoing')->default(true);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('category_id')
                ->references('id')
                ->on('categories')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
