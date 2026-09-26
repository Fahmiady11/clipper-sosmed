<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\TikTokController;
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
    Route::get('/autopilot', fn() => view('autopilot'))->name('autopilot');

    // TikTok OAuth (redirect URI registered on the TikTok app → /tiktok/callback)
    Route::get('/tiktok/connect', [TikTokController::class, 'connect'])->name('tiktok.connect');
    Route::get('/tiktok/callback', [TikTokController::class, 'callback'])->name('tiktok.callback');
});
