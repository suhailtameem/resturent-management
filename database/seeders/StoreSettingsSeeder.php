<?php

namespace Database\Seeders;

use App\Models\StoreOperation;
use App\Models\StoreSettings;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class StoreSettingsSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        StoreSettings::query()->firstOrCreate(['id' => 1], StoreSettings::defaultAttributes());
        StoreOperation::current();
    }
}
