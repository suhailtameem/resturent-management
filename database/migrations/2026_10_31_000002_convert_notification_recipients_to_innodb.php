<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE `notification_recipients` ENGINE=InnoDB');

        foreach ([
            'notification_id' => 'notifications',
            'user_id' => 'users',
            'device_token_id' => 'device_tokens',
        ] as $column => $referencedTable) {
            $hasForeignKey = DB::table('information_schema.key_column_usage')
                ->where('constraint_schema', DB::getDatabaseName())
                ->where('table_name', 'notification_recipients')
                ->where('column_name', $column)
                ->whereNotNull('referenced_table_name')
                ->exists();

            if (! $hasForeignKey) {
                Schema::table('notification_recipients', function (Blueprint $table) use ($column, $referencedTable): void {
                    $table->foreign($column)
                        ->references('id')
                        ->on($referencedTable)
                        ->cascadeOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        // Keep the transactional storage engine when rolling back feature fields.
    }
};
