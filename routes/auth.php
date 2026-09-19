<?php

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

// Invitados: inicio de sesión. Sin registro público ni recuperación por correo (fuera de alcance).
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login')->name('login.attempt');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');
