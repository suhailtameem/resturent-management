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
            DB::statement('ALTER TABLE `users` ENGINE=InnoDB');
            DB::statement('ALTER TABLE `staff_users` ENGINE=InnoDB');
            DB::statement('ALTER TABLE `notifications` ENGINE=InnoDB');
        }

        if (Schema::hasTable('device_tokens')) {
            if (DB::connection()->getDriverName() !== 'mysql') {
                throw new RuntimeException('The device_tokens table already exists; inspect it before continuing this migration.');
            }

            DB::statement('ALTER TABLE `device_tokens` ENGINE=InnoDB');
            DB::statement('ALTER TABLE `device_tokens` MODIFY `token` VARCHAR(512) CHARACTER SET ascii COLLATE ascii_bin NOT NULL');

            $hasUniqueTokenIndex = DB::table('information_schema.statistics')
                ->where('table_schema', DB::getDatabaseName())
                ->where('table_name', 'device_tokens')
                ->where('column_name', 'token')
                ->where('non_unique', 0)
                ->exists();

            if (! $hasUniqueTokenIndex) {
                Schema::table('device_tokens', function (Blueprint $table): void {
                    $table->unique('token');
                });
            }

            foreach ([
                'user_id' => ['users', 'cascade'],
                'staff_user_id' => ['staff_users', 'cascade'],
            ] as $column => [$referencedTable, $deleteAction]) {
                $hasForeignKey = DB::table('information_schema.key_column_usage')
                    ->where('constraint_schema', DB::getDatabaseName())
                    ->where('table_name', 'device_tokens')
                    ->where('column_name', $column)
                    ->whereNotNull('referenced_table_name')
                    ->exists();

                if (! $hasForeignKey) {
                    Schema::table('device_tokens', function (Blueprint $table) use ($column, $referencedTable, $deleteAction): void {
                        $table->foreign($column)
                            ->references('id')
                            ->on($referencedTable)
                            ->onDelete($deleteAction);
                    });
                }
            }

            return;
        }

        Schema::create('device_tokens', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('staff_user_id')->nullable()->constrained('staff_users')->cascadeOnDelete();
            $token = $table->string('token', 512);
            if (DB::connection()->getDriverName() === 'mysql') {
                $token->charset('ascii')->collation('ascii_bin');
            }
            $token->unique();
            $table->string('platform', 10);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};
