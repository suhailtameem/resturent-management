<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `device_tokens` ENGINE=InnoDB');
            DB::statement('ALTER TABLE `notifications` ENGINE=InnoDB');
        }
        Schema::table('notifications', function (Blueprint $table): void {
            $table->string('destination')->nullable();
            $table->string('coupon_code', 100)->nullable();
            $table->unsignedInteger('total_recipients')->default(0);
            $table->unsignedInteger('successful_sends')->default(0);
            $table->unsignedInteger('failed_sends')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->string('idempotency_key', 80)->nullable()->unique();
        });
        Schema::create('notification_recipients', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('notification_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_token_id')->nullable()->constrained('device_tokens')->nullOnDelete();
            $table->string('status', 20)->default('queued');
            $table->string('fcm_message_id')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamps();
            $table->index(['notification_id', 'status']);
            $table->unique(['notification_id', 'device_token_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_recipients');
        Schema::table('notifications', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['destination', 'coupon_code', 'total_recipients', 'successful_sends', 'failed_sends', 'idempotency_key']);
        });
    }
};
