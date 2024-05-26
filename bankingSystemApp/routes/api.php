<?php

use App\Http\Controllers\BankingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/
Route::post('/users', [BankingController::class, 'createUser']);
Route::post('/login', [BankingController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/', [BankingController::class, 'showTransactions']);
    Route::get('/deposit', [BankingController::class, 'showDeposits']);
    Route::post('/deposit', [BankingController::class, 'deposit']);
    Route::get('/withdrawal', [BankingController::class, 'showWithdrawals']);
    Route::post('/withdrawal', [BankingController::class, 'withdrawal']);
});
