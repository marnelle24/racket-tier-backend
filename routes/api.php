<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BroadcastAuthController;
use App\Http\Controllers\FacilityController;
use App\Http\Controllers\FacilityJoinController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

// Public: check if facility exists (404 if not) — used for facility page 404 handling
Route::get('/facilities/check/{id}', [FacilityController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    // Broadcast auth: custom controller returns failing channel name on 403 for debugging.
    Route::match(['get', 'post'], 'broadcasting/auth', BroadcastAuthController::class);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', function (Request $request) {
        return response()->json([
            'success' => true,
            'message' => 'User retrieved.',
            'data' => ['user' => $request->user()],
        ]);
    });
    Route::put('/me', [UserController::class, 'update']);
    Route::put('/me/password', [UserController::class, 'changePassword']);

    Route::post('/facilities/join', [FacilityJoinController::class, 'join']);
    Route::post('/facilities', [FacilityController::class, 'store']);
    Route::get('/facilities', [FacilityController::class, 'index']);
    Route::get('/facilities/mine', [FacilityController::class, 'mine']);
    Route::get('/facilities/{id}/presence', [FacilityController::class, 'presence']);
    Route::get('/facilities/{id}/players', [FacilityController::class, 'players']);
    Route::put('/facilities/{id}', [FacilityController::class, 'update']);
    Route::delete('/facilities/{id}', [FacilityController::class, 'destroy']);

    Route::get('/games', [GameController::class, 'index']);
    Route::post('/games', [GameController::class, 'store']);
    Route::post('/games/{game}/start', [GameController::class, 'start']);
    Route::post('/games/{game}/invite', [GameController::class, 'invite']);
    Route::put('/games/{game}/invite', [GameController::class, 'updateInvites']);
    Route::post('/games/{game}/respond', [GameController::class, 'respond']);
    Route::post('/games/{game}/result', [GameController::class, 'submitResult']);
    Route::post('/games/{game}/confirm', [GameController::class, 'confirm']);
    Route::post('/games/{game}/leave', [GameController::class, 'leave']);
    Route::post('/games/{game}/abort', [GameController::class, 'abort']);

    Route::get('/stats/me', [StatsController::class, 'me']);
    Route::get('/stats/me/history', [StatsController::class, 'history']);
    Route::get('/stats/facility/{facility_id}', [StatsController::class, 'facility']);

    // testing only route`
});