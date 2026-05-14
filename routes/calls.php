<?php

use App\Http\Controllers\VideoCallController;
use App\Http\Middleware\EnsureSecureVideoCalls;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Call Routes
|--------------------------------------------------------------------------
| Names: calls.ice-servers, calls.initiate, calls.signal, calls.answer,
| calls.decline, calls.end, calls.group.initiate, calls.group.signal,
| calls.group.answer, calls.group.end.
*/

Route::middleware(['auth', 'verified', EnsureSecureVideoCalls::class])->prefix('api/calls')->name('calls.')->group(function (): void {
    Route::get('/ice-servers', [VideoCallController::class, 'iceServers'])->name('ice-servers');
    Route::post('/initiate', [VideoCallController::class, 'initiate'])->middleware('throttle:video-calls')->name('initiate');

    Route::prefix('group')->name('group.')->group(function (): void {
        Route::post('/initiate', [VideoCallController::class, 'initiateGroup'])->middleware('throttle:video-calls')->name('initiate');
        Route::post('/{call}/signal', [VideoCallController::class, 'signalGroup'])->name('signal');
        Route::post('/{call}/answer', [VideoCallController::class, 'answerGroup'])->name('answer');
        Route::post('/{call}/end', [VideoCallController::class, 'endGroup'])->name('end');
    });

    Route::post('/{call}/signal', [VideoCallController::class, 'signal'])->name('signal');
    Route::post('/{call}/answer', [VideoCallController::class, 'answer'])->name('answer');
    Route::post('/{call}/decline', [VideoCallController::class, 'decline'])->name('decline');
    Route::post('/{call}/end', [VideoCallController::class, 'end'])->name('end');
});
