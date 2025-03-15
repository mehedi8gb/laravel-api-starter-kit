<?php

use App\Http\Controllers\AuthController;
use App\Http\Middleware\JwtMiddleware;
use App\Http\Middleware\RefreshTokenMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::group(['prefix' => 'auth'], function () {
    // Authentication routes
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    // Password management
    Route::post('forget', [AuthController::class, 'forget']);
    Route::post('validate', [AuthController::class, 'validateCode']);
    Route::post('reset', [AuthController::class, 'resetPassword']);

    // Token management
    Route::post('refresh', [AuthController::class, 'refresh'])->middleware([RefreshTokenMiddleware::class]);
    Route::post('logout', [AuthController::class, 'logout'])->middleware([JwtMiddleware::class]);
});

Route::middleware([JwtMiddleware::class])->group(function () {
    Route::get('me', [AuthController::class, 'me']);
});
