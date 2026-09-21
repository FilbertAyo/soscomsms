<?php

use App\Http\Controllers\Api\V1\BalanceController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\SenderIdController;
use App\Http\Controllers\Api\V1\SmsController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['api.key', 'throttle:api-v1'])->group(function (): void {
    Route::post('/sms/send', [SmsController::class, 'send']);
    Route::post('/sms/bulk', [SmsController::class, 'bulk']);
    Route::get('/sms/{sms}', [SmsController::class, 'show'])->whereNumber('sms');
    Route::get('/sms/{sms}/messages', [SmsController::class, 'messages'])->whereNumber('sms');
    Route::get('/messages/{message}', [SmsController::class, 'message'])->whereNumber('message');
    Route::get('/balance', [BalanceController::class, 'show']);
    Route::get('/sender-ids', [SenderIdController::class, 'index']);
    Route::get('/reports', [ReportController::class, 'index']);
});
