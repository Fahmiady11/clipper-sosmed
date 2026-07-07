<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisterController;
use Illuminate\Support\Facades\Route;

// Auth routes (public)
Route::get('/login', [LoginController::class, 'show'])->name('login');
Route::post('/login', [LoginController::class, 'store']);
Route::get('/register', [RegisterController::class, 'show'])->name('register');
Route::post('/register', [RegisterController::class, 'store']);
Route::post('/logout', [LogoutController::class, 'destroy'])->name('logout');

// TODO: Google OAuth via Socialite
// Route::get('/auth/google', [LoginController::class, 'redirectToGoogle'])->name('auth.google');
// Route::get('/auth/google/callback', [LoginController::class, 'handleGoogleCallback']);

// Protected routes
Route::middleware('auth')->group(function () {
    Route::get('/', fn() => view('studio'))->name('home');
});
