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
        Schema::create('delivery_neighborhoods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_city_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->decimal('delivery_fee', 8, 2);
            $table->decimal('minimum_order_amount', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->unique(['delivery_city_id', 'name']);
            $table->timestamps();
        });
    }

    /**
     * Drop the delivery neighborhoods table.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_neighborhoods');
    }
};
