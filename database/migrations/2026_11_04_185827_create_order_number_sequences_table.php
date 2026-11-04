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
        Schema::create('order_number_sequences', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->engine = 'InnoDB';
        });

        $highestExistingNumber = 10000;
        foreach (DB::table('orders')->select('order_number')->orderBy('id')->cursor() as $order) {
            if (ctype_digit((string) $order->order_number)) {
                $highestExistingNumber = max($highestExistingNumber, (int) $order->order_number);
            }
        }

        $startingSequence = $highestExistingNumber - 10000;
        if ($startingSequence > 0) {
            DB::table('order_number_sequences')->insert([
                'id' => $startingSequence,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_number_sequences');
    }
};
