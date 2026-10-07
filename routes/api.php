<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WeatherController;
use Illuminate\Support\Facades\Route;

Route::post('register', [AuthController::class, 'register'])->middleware('throttle:register');
Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::apiResource('posts', PostController::class)->except('update');
    Route::patch('posts/{post}', [PostController::class, 'update']);
    Route::apiResource('users', UserController::class)->only(['index', 'show']);
    Route::get('weather', WeatherController::class);
});
