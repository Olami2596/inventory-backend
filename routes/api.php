<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryTransactionController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware(['auth:sanctum', 'active']);

Route::prefix('v1')->group(function () {
    Route::post('/register', [RegisterController::class, 'register'])->middleware('throttle:5,1');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:5,1');
    Route::post('/invitations/accept/{token}', [InvitationController::class, 'accept'])->middleware('throttle:10,1');
    Route::post('/password/forgot', [PasswordResetController::class, 'request'])->middleware('throttle:3,1');
    Route::post('/password/reset', [PasswordResetController::class, 'reset'])->middleware('throttle:10,1');

    Route::middleware(['auth:sanctum', 'active', 'throttle:60,1'])->group(function () {
        Route::post('/logout', [LogoutController::class, 'logout']);
        Route::post('/me/revoke-tokens', [UserController::class, 'revokeAllMyTokens']);

        Route::get('/dashboard', [DashboardController::class, 'summary']);

        Route::apiResource('categories', CategoryController::class)->only(['index', 'show']);
        Route::apiResource('suppliers', SupplierController::class)->only(['index', 'show']);
        Route::apiResource('products', ProductController::class)->only(['index', 'show']);
        Route::apiResource('transactions', InventoryTransactionController::class)->only(['index', 'show', 'store']);

        Route::middleware('role:owner,admin')->group(function () {
            Route::apiResource('categories', CategoryController::class)->only(['store', 'update', 'destroy']);
            Route::apiResource('suppliers', SupplierController::class)->only(['store', 'update', 'destroy']);
            Route::apiResource('products', ProductController::class)->only(['store', 'update', 'destroy']);

            Route::get('/users', [UserController::class, 'index']);
            Route::patch('/users/{user}/deactivate', [UserController::class, 'deactivate']);
            Route::patch('/users/{user}/reactivate', [UserController::class, 'reactivate']);
            Route::post('/users/{user}/revoke-tokens', [UserController::class, 'revokeUserTokens']);

            Route::get('/invitations', [InvitationController::class, 'index']);
            Route::post('/invitations', [InvitationController::class, 'store']);
            Route::patch('/invitations/{invitation}/cancel', [InvitationController::class, 'cancel']);
        });
    });
});
