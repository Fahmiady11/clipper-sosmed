<?php

use App\Http\Controllers\ClipController;
use App\Http\Controllers\DownloadController;
use App\Http\Controllers\MetaController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\RenderController;
use App\Http\Controllers\TikTokController;
use Illuminate\Support\Facades\Route;

// New Studio API (auth required)
Route::middleware('auth')->group(function () {
    Route::get('/meta', [MetaController::class, 'show']);

    Route::get('/projects', [ProjectController::class, 'index']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::get('/projects/{projectId}/status', [ProjectController::class, 'status']);
    Route::delete('/projects/{projectId}', [ProjectController::class, 'destroy']);

    Route::post('/clips/{clipId}/render', [RenderController::class, 'render']);
    Route::get('/clips/{clipId}/render-status', [RenderController::class, 'renderStatus']);
    Route::get('/clips/{clipId}/caption', [RenderController::class, 'caption']);
    Route::get('/clips/{clipId}/download', [DownloadController::class, 'download']);

    Route::get('/tiktok/accounts', [TikTokController::class, 'accounts']);
    Route::delete('/tiktok/accounts/{accountId}', [TikTokController::class, 'disconnect']);
    Route::get('/tiktok/accounts/{accountId}/creator-info', [TikTokController::class, 'creatorInfo']);
    Route::post('/clips/{clipId}/tiktok', [TikTokController::class, 'upload']);
    Route::get('/clips/{clipId}/tiktok-status', [TikTokController::class, 'status']);
});

// Legacy routes (backward compat — keep as-is)
Route::post('/clips', [ClipController::class, 'store']);
Route::get('/clips/{batchId}/status', [ClipController::class, 'batchStatus']);
Route::post('/clips/{clipId}/generate', [ClipController::class, 'generate']);
Route::get('/clip/{id}/status', [ClipController::class, 'status']);
Route::get('/clip/{id}/download', [ClipController::class, 'download']);
Route::get('/clip/{id}/preview', [ClipController::class, 'preview']);
Route::post('/test/subtitle', [ClipController::class, 'testSubtitle']);
