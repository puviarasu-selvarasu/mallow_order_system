<?php

use App\Http\Controllers\Api\CustomerOrderController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

Route::post('/orders', [OrderController::class, 'store']);
Route::get('/customers/orders', [CustomerOrderController::class, 'history']);
Route::get('/products/low-stock', [ProductController::class, 'lowStock']);
