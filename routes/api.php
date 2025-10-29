<?php

use App\Http\Controllers\Api\BalanceController;
use Illuminate\Support\Facades\Route;

Route::post('/deposit', [BalanceController::class, 'deposit']);
Route::post('/withdraw', [BalanceController::class, 'withdraw']);
Route::post('/transfer', [BalanceController::class, 'transfer']);
Route::get('/balance/{userId}', [BalanceController::class, 'getBalance']);

