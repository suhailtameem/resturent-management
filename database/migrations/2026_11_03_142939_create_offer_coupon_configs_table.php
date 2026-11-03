<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offer_coupon_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('offer_id')->unique();
            $table->string('code', 32)->unique();
            $table->enum('usage_limit_per_user', ['once', 'twice', 'three_times', 'unlimited'])->default('once');
            $table->unsignedInteger('total_usage_limit')->nullable();
            $table->unsignedInteger('total_used_count')->default(0);
            $table->timestamps();

            $table->foreign('offer_id')
                ->references('id')
                ->on('offers')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_coupon_configs');
    }
};
