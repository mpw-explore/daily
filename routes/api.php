<?php

use App\Http\Controllers\DailyLogController;
use Illuminate\Support\Facades\Route;

Route::get('/daily-logs', [DailyLogController::class, 'index']);
Route::post('/daily-logs', [DailyLogController::class, 'store']);
Route::post('/daily-logs/batch', [DailyLogController::class, 'batchStore']);
Route::put('/daily-logs/{id}', [DailyLogController::class, 'update']);
Route::delete('/daily-logs/{id}', [DailyLogController::class, 'destroy']);

