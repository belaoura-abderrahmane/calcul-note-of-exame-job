<?php

use App\Http\Controllers\PrintController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::middleware('auth')->group(function () {
    Route::get('/competitions/{competition}/print', [PrintController::class, 'competition'])
        ->name('competitions.print');
});

// توجيه "login" الافتراضي إلى صفحة دخول لوحة التحكم
Route::get('/login', fn () => redirect()->route('filament.admin.auth.login'))->name('login');
