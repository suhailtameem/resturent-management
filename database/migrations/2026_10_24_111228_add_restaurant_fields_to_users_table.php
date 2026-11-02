<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->after('name');
            $table->string('full_name', 100)->nullable()->after('phone');
            $table->enum('preferred_language', ['ar', 'en'])->default('ar')->after('email');
            $table->boolean('is_vip')->default(false)->after('preferred_language');
            $table->unsignedInteger('total_orders_count')->default(0)->after('is_vip');
            $table->decimal('total_spent_amount', 10, 2)->default(0.00)->after('total_orders_count');
            $table->timestamp('last_order_at')->nullable()->after('total_spent_amount');
            $table->enum('status', ['active', 'blocked'])->default('active')->after('last_order_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone',
                'full_name',
                'preferred_language',
                'is_vip',
                'total_orders_count',
                'total_spent_amount',
                'last_order_at',
                'status',
            ]);
        });
    }
};
