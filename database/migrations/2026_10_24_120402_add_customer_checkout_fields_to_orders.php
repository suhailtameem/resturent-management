<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('delivery_mode', 20)->default('pickup');
            $table->string('idempotency_key', 128)->nullable();
            $table->index(['user_id', 'created_at']);
            $table->unique(['user_id', 'idempotency_key']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->text('special_instructions')->nullable();
            $table->string('image_url_snapshot', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['special_instructions', 'image_url_snapshot']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropUnique(['user_id', 'idempotency_key']);
            $table->dropColumn(['delivery_mode', 'idempotency_key']);
        });
    }
};
