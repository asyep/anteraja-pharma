<?php

use App\Http\Controllers\PharmaController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->group(function () {
    Route::get('session', [PharmaController::class, 'session']);
    Route::post('login', [PharmaController::class, 'login'])->middleware('throttle:10,1');
    Route::get('health', fn () => ['status' => 'ok', 'service' => 'Anteraja Pharma']);
    Route::post('webhooks/automation', [WebhookController::class, 'automation'])->middleware('throttle:30,1');
    Route::post('webhooks/telegram', [WebhookController::class, 'telegram'])->middleware('throttle:60,1');
    Route::middleware('auth')->group(function () {
        Route::post('logout', [PharmaController::class, 'logout']);
        Route::get('dashboard', [PharmaController::class, 'dashboard']);
        Route::get('directory', [PharmaController::class, 'directory']);
        Route::get('shipments', [PharmaController::class, 'index']);
        Route::post('shipments', [PharmaController::class, 'create']);
        Route::get('shipments/{id}', [PharmaController::class, 'detail'])->whereNumber('id');
        Route::post('shipments/{id}/actions/{action}', [PharmaController::class, 'action'])->whereNumber('id')->middleware('throttle:60,1');
        Route::get('messages', [PharmaController::class, 'messages']);
        Route::post('messages/{id}/retry', [PharmaController::class, 'retry'])->whereNumber('id');
        Route::get('reports', [PharmaController::class, 'reports']);
        Route::post('reports', [PharmaController::class, 'report']);
        Route::get('reports/{id}/export', [PharmaController::class, 'export'])->whereNumber('id');
        Route::post('settings', [PharmaController::class, 'settings']);
    });
});
