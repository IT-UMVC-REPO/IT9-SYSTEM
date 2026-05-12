<?php

use App\Http\Controllers\MessageAttachmentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Messaging Routes
|--------------------------------------------------------------------------
| Names: messages.inbox, messages.conversation, messages.group.
*/

Route::middleware(['auth', 'verified', 'role:customer,vendor,rider,admin'])->prefix('messages')->name('messages.')->group(function (): void {
    Route::livewire('/', 'pages::messages.inbox')->name('inbox');
    Route::get('/attachments/{attachment}', [MessageAttachmentController::class, 'show'])->name('attachments.show');
    Route::livewire('/{conversationReference}', 'pages::messages.conversation')->name('conversation');
});

Route::middleware(['auth', 'verified', 'role:customer,vendor,rider,admin'])->group(function (): void {
    Route::get('/groups/attachments/{attachment}', [MessageAttachmentController::class, 'showGroup'])->name('messages.group-attachments.show');
    Route::livewire('/groups/{groupId}', 'pages::messages.group-conversation')->name('messages.group');
});
