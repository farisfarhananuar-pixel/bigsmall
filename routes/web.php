<?php

use App\Http\Controllers\GameController;
use Illuminate\Support\Facades\Route;

// Main game page - no login needed
Route::get('/', [GameController::class, 'index'])->name('game.index');

// Game actions
Route::post('/bet', [GameController::class, 'placeBet'])->name('game.bet');
Route::post('/spin', [GameController::class, 'spin'])->name('game.spin');
Route::post('/topup', [GameController::class, 'topUp'])->name('game.topup');
Route::get('/status', [GameController::class, 'getStatus'])->name('game.status');
