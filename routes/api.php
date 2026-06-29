<?php

use App\Http\Controllers\DaftraCredentialsController;
use App\Http\Controllers\ElevenLabsController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\TranscriptController;
use App\Http\Controllers\WebhookEndpointController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/elevenlabs/token', [ElevenLabsController::class, 'token']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders/daftra', [OrderController::class, 'sendToDaftra']);
    Route::post('/transcript/suggest', [TranscriptController::class, 'suggest']);
    Route::apiResource('webhook-endpoints', WebhookEndpointController::class);
    Route::get('/daftra/credentials', [DaftraCredentialsController::class, 'show']);
    Route::put('/daftra/credentials', [DaftraCredentialsController::class, 'update']);
    Route::delete('/daftra/credentials', [DaftraCredentialsController::class, 'destroy']);
    Route::post('/daftra/interpret', [OrderController::class, 'interpret']);
});
