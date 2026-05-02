<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Messaging Routes
|--------------------------------------------------------------------------
| Names: messages.inbox, messages.conversation, messages.group.
*/

Route::middleware(['auth', 'verified', 'role:customer,vendor,admin'])->prefix('messages')->name('messages.')->group(function (): void {
    Route::livewire('/', 'pages::messages.inbox')->name('inbox');
    Route::livewire('/{conversationReference}', 'pages::messages.conversation')->name('conversation');
});

Route::middleware(['auth', 'verified', 'role:customer,vendor,admin'])->group(function (): void {
    Route::livewire('/groups/{groupId}', 'pages::messages.group-conversation')->name('messages.group');
});
