<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title_ar', 50);
            $table->string('title_en', 50)->nullable();
            $table->string('city_ar', 50)->nullable();
            $table->string('city_en', 50)->nullable();
            $table->string('district_ar', 100);
            $table->string('district_en', 100)->nullable();
            $table->string('street_details_ar', 255)->nullable();
            $table->string('street_details_en', 255)->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
    }
};
