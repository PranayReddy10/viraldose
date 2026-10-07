<?php

use App\Http\Controllers\Api\AgentController;
use App\Http\Middleware\AgentToken;
use Illuminate\Support\Facades\Route;

/*
| Content agent API (prefix /api). Every request needs: Authorization: Bearer <token>.
| Posts created here are always saved as drafts for an editor to review and publish.
*/
Route::prefix('agent')->middleware([AgentToken::class, 'throttle:60,1'])->group(function () {
    Route::get('context', [AgentController::class, 'context']);
    Route::get('posts', [AgentController::class, 'index']);
    Route::post('posts', [AgentController::class, 'store'])->middleware('throttle:20,60');
});
