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
        Schema::create('delivery_cities', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->enum('service_status', ['active', 'coming_soon', 'preorder_only'])->default('active');
            $table->timestamps();
        });
    }

    /**
     * Drop the delivery cities table.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_cities');
    }
};
