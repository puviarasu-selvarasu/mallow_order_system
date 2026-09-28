<?php

use App\Http\Controllers\Api\CustomerOrderController;
use App\Http\Controllers\Api\OrderController;
use Illuminate\Support\Facades\Route;

Route::post('/orders', [OrderController::class, 'store']);
Route::get('/customers/orders', [CustomerOrderController::class, 'history']);
