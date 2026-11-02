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
        Schema::table('users', function (Blueprint $table): void {
            $table->string('email')->nullable()->change();
            $table->timestamp('phone_verified_at')->nullable()->after('phone');
            $table->timestamp('manager_verified_at')->nullable()->after('phone_verified_at');
            $table->foreignId('manager_verified_by_staff_user_id')
                ->nullable()
                ->after('manager_verified_at')
                ->constrained('staff_users')
                ->nullOnDelete();
        });

        DB::table('users')->where('email', '')->update(['email' => null]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('manager_verified_by_staff_user_id');
            $table->dropColumn(['phone_verified_at', 'manager_verified_at']);
        });
    }
};
