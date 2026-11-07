<?php

use App\Http\Controllers\MenuPageController;
use App\Models\StoreSettings;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('apps', [
        'customerAppUrl' => config('app_downloads.customer'),
        'managerAppUrl' => config('app_downloads.manager'),
    ]);
});

Route::get('/menu', MenuPageController::class)->name('menu');

Route::get('/privacy-policy', function () {
    $settings = StoreSettings::query()->first();

    return view('privacy-policy', [
        'storeNameAr' => $settings?->store_name_ar ?: 'أرواق العنب',
        'storeNameEn' => $settings?->store_name_en ?: 'Arwaq Al-Eneab',
        'supportPhone' => '+966530697231',
    ]);
})->name('privacy-policy');
