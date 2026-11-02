<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('icon_code', 50)->nullable();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_best_seller')->default(false);
            $table->string('badge_text_ar', 100)->nullable();
        });

        Schema::table('options_groups', function (Blueprint $table) {
            $table->enum('option_category', ['flavor', 'add_on'])->default('add_on');
        });
    }

    public function down(): void
    {
        Schema::table('options_groups', function (Blueprint $table) {
            $table->dropColumn('option_category');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['is_featured', 'is_best_seller', 'badge_text_ar']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('icon_code');
        });
    }
};
