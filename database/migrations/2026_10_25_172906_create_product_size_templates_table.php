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
        Schema::create('product_size_templates', function (Blueprint $table) {
            $table->id();
            $table->enum('size_key', ['small', 'medium', 'large', 'jumbo', 'family']);
            $table->string('name_ar', 50);
            $table->string('name_en', 50)->nullable();
            $table->unsignedInteger('piece_count');
            $table->string('portion_description_ar', 100)->nullable();
            $table->string('portion_description_en', 100)->nullable();
            $table->decimal('price', 8, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_size_templates');
    }
};
