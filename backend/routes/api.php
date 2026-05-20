<?php

use App\Http\Controllers\GameController;
use App\Http\Controllers\RoomController;
use App\Http\Middleware\AuthenticatePlayer;
use Illuminate\Support\Facades\Route;

Route::post('/rooms', [RoomController::class, 'store']);
Route::post('/rooms/{code}/join', [RoomController::class, 'join']);

Route::middleware([AuthenticatePlayer::class])->group(function () {
    Route::get('/games/{game}', [GameController::class, 'show']);
    Route::post('/games/{game}/play', [GameController::class, 'play']);
    Route::post('/games/{game}/end-turn', [GameController::class, 'endTurn']);
});
