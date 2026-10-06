<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware(['auth:sanctum', 'tenant'])->group(function () {
    Route::get('/yo', [AuthController::class, 'yo']);
    Route::post('/logout', [AuthController::class, 'logout']);
});

