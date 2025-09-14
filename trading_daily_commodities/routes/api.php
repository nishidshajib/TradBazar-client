<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\BargainController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    // Products
    Route::apiResource('products', ProductController::class);
    Route::get('/vendors/{user}/products', [ProductController::class, 'vendorProducts']); // Local Vendor Showcase

    // Orders
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus']);

    // Bargains
    Route::get('/bargains', [BargainController::class, 'index']);
    Route::post('/bargains', [BargainController::class, 'store']);
    Route::post('/bargains/{bargain}/respond', [BargainController::class, 'respond']);
});
