<?php

use App\Http\Controllers\ClipController;
use Illuminate\Support\Facades\Route;

Route::post('/clips', [ClipController::class, 'store']);
Route::get('/clips/{batchId}/status', [ClipController::class, 'batchStatus']);
Route::post('/clips/{clipId}/generate', [ClipController::class, 'generate']);
Route::get('/clip/{id}/status', [ClipController::class, 'status']);
Route::get('/clip/{id}/download', [ClipController::class, 'download']);
Route::get('/clip/{id}/preview', [ClipController::class, 'preview']);
Route::post('/test/subtitle', [ClipController::class, 'testSubtitle']);
