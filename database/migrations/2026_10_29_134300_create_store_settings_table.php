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
        Schema::create('store_settings', function (Blueprint $table) {
            $table->id();
            $table->string('store_name_ar', 150)->default('أرواق العنب');
            $table->string('store_name_en', 150)->nullable()->default('Arwaq Al-Eneab');
            $table->string('address', 255)->nullable();
            $table->string('support_phone', 20)->nullable();
            $table->boolean('cash_on_delivery_enabled')->default(true);
            $table->boolean('online_enabled')->default(true);
            $table->boolean('auto_accept_prepaid_orders')->default(false);
            $table->string('currency_label_ar', 20)->default('ر.س');
            $table->string('currency_label_en', 20)->default('SAR');
            $table->timestamps();
        });
    }

    /**
     * Drop the store settings table.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_settings');
    }
};
