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
        Schema::create('store_operations', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_open')->default(true);
            $table->timestamp('paused_until')->nullable();
            $table->string('timezone', 64)->default('Asia/Riyadh');
            $table->unsignedSmallInteger('orders_per_hour_limit')->default(18);
            $table->unsignedSmallInteger('estimated_prep_time_minutes')->default(30);
            $table->boolean('rush_extra_time_enabled')->default(false);
            $table->unsignedSmallInteger('rush_extra_time_minutes')->default(15);
            $table->json('weekly_schedule')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Drop the store operations table.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_operations');
    }
};
