<?php

use App\Models\StoreOperation;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function (): void {
    StoreOperation::pausedAndExpired()->update([
        'is_open' => true,
        'paused_until' => null,
    ]);
})->everyMinute();
