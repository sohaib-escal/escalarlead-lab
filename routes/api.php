<?php

use App\Http\Controllers\Api\AgentTurnController;
use Illuminate\Support\Facades\Route;

/*
 | The WhatsApp gateway (zailer) is the only caller. Everything here is signed
 | with the shared secret and throttled — it creates leads and spends model
 | credits, so it is never open.
 */
Route::middleware(['agent.signature', 'throttle:120,1'])->group(function () {
    Route::post('/agent/turn', AgentTurnController::class)->name('api.agent.turn');
});
