<?php

use App\Http\Controllers\MenuPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('apps', [
        'customerAppUrl' => config('app_downloads.customer'),
        'managerAppUrl' => config('app_downloads.manager'),
    ]);
});

Route::get('/menu', MenuPageController::class)->name('menu');
