<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class , 'login']);
    Route::post('/refresh', [AuthController::class , 'refresh']);
    Route::post('/logout', [AuthController::class , 'logout']);
});

Route::prefix('users')->group(function () {
    Route::post('/', [UserController::class , 'create']);
    Route::post('/change-password', [UserController::class , 'changePassword']);
});
