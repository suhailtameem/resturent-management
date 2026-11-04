<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $hasForeignKey = Schema::hasForeignKey('order_items', ['product_id']);
        if ($hasForeignKey) {
            Schema::table('order_items', function (Blueprint $table): void {
                $table->dropForeign(['product_id']);
            });
        } elseif (Schema::hasIndex('order_items', 'order_items_product_id_foreign')) {
            Schema::table('order_items', function (Blueprint $table): void {
                $table->dropIndex('order_items_product_id_foreign');
            });
        }

        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable()->change();
        });

        Schema::table('order_items', function (Blueprint $table) use ($hasForeignKey): void {
            if ($hasForeignKey) {
                $table->foreign('product_id')
                    ->references('id')
                    ->on('products')
                    ->nullOnDelete();
            } else {
                $table->index('product_id', 'order_items_product_id_foreign');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('order_items')->whereNull('product_id')->exists()) {
            throw new RuntimeException(
                'Cannot restore required product references after products have been deleted.',
            );
        }

        $hasForeignKey = Schema::hasForeignKey('order_items', ['product_id']);
        if ($hasForeignKey) {
            Schema::table('order_items', function (Blueprint $table): void {
                $table->dropForeign(['product_id']);
            });
        } elseif (Schema::hasIndex('order_items', 'order_items_product_id_foreign')) {
            Schema::table('order_items', function (Blueprint $table): void {
                $table->dropIndex('order_items_product_id_foreign');
            });
        }

        Schema::table('order_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('product_id')->nullable(false)->change();
        });

        Schema::table('order_items', function (Blueprint $table) use ($hasForeignKey): void {
            if ($hasForeignKey) {
                $table->foreign('product_id')
                    ->references('id')
                    ->on('products')
                    ->cascadeOnDelete();
            } else {
                $table->index('product_id', 'order_items_product_id_foreign');
            }
        });
    }
};
