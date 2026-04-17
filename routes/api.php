<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\CouponController;

/*
|--------------------------------------------------------------------------
| Public Routes — No authentication required
|--------------------------------------------------------------------------
*/

// Auth
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);
});

// Products & Categories (anyone can browse)
Route::get('/categories',    [CategoryController::class, 'index']);
Route::get('/products',      [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show']);

/*
|--------------------------------------------------------------------------
| Protected Routes — Require valid Sanctum Bearer token
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::prefix('auth')->group(function () {
        Route::get('/profile',     [AuthController::class, 'profile']);
        Route::post('/logout',     [AuthController::class, 'logout']);
        Route::post('/logout-all', [AuthController::class, 'logoutAll']);
    });

    // Cart
    Route::prefix('cart')->group(function () {
        Route::get('/',               [CartController::class, 'index']);
        Route::post('/add',           [CartController::class, 'add']);
        Route::put('/update',         [CartController::class, 'update']);
        Route::delete('/remove/{id}', [CartController::class, 'remove']);
    });

    // Orders
    Route::prefix('orders')->group(function () {
        Route::get('/',      [OrderController::class, 'index']);
        Route::post('/',     [OrderController::class, 'store']);
        Route::get('/{id}',  [OrderController::class, 'show']);
        // Coupons
        Route::post('/coupons/apply', [CouponController::class, 'apply']);
    });

    /*
    |--------------------------------------------------------------------------
    | Admin Routes — Require valid Sanctum token + admin role
    |--------------------------------------------------------------------------
    */

    Route::prefix('admin')->middleware('is_admin')->group(function () {
        // inside Route::prefix('admin')->middleware('is_admin')
        Route::put('/orders/{id}/status', [OrderController::class, 'updateStatus']);
        Route::post('/categories',        [CategoryController::class, 'store']);
        Route::post('/products',          [ProductController::class, 'store']);
        Route::put('/products/{id}',      [ProductController::class, 'update']);
        Route::delete('/products/{id}',   [ProductController::class, 'destroy']);
        // Coupons
        Route::get('/coupons',          [CouponController::class, 'index']);
        Route::post('/coupons',         [CouponController::class, 'store']);
        Route::put('/coupons/{id}',     [CouponController::class, 'update']);
        Route::delete('/coupons/{id}',  [CouponController::class, 'destroy']);
    });
});
