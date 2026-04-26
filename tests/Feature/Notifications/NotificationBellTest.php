<?php

use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\User;
use Livewire\Livewire;

test('unread count reflects db state', function () {
    $user = User::factory()->create();

    Notification::query()->create([
        'user_id' => $user->getKey(),
        'title' => 'Fresh delivery update',
        'message' => 'Your order is on the way.',
        'type' => NotificationType::OrderUpdate,
        'is_read' => false,
    ]);

    Notification::query()->create([
        'user_id' => $user->getKey(),
        'title' => 'Earlier read note',
        'message' => 'This one is already read.',
        'type' => NotificationType::System,
        'is_read' => true,
    ]);

    Livewire::actingAs($user)
        ->test('notifications.notification-bell')
        ->assertSee('1')
        ->assertSee('Fresh delivery update')
        ->assertSee('Earlier read note');
});

test('mark all read sets notifications to read for the authenticated user only', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    Notification::query()->create([
        'user_id' => $user->getKey(),
        'title' => 'Pending review',
        'message' => 'First unread notification.',
        'type' => NotificationType::System,
        'is_read' => false,
    ]);

    Notification::query()->create([
        'user_id' => $user->getKey(),
        'title' => 'Another unread note',
        'message' => 'Second unread notification.',
        'type' => NotificationType::Message,
        'is_read' => false,
    ]);

    Notification::query()->create([
        'user_id' => $otherUser->getKey(),
        'title' => 'Other user note',
        'message' => 'Should stay unread.',
        'type' => NotificationType::OrderUpdate,
        'is_read' => false,
    ]);

    Livewire::actingAs($user)
        ->test('notifications.notification-bell')
        ->call('markAllAsRead');

    expect(Notification::query()->where('user_id', $user->getKey())->where('is_read', false)->count())->toBe(0);
    expect(Notification::query()->where('user_id', $otherUser->getKey())->where('is_read', false)->count())->toBe(1);
});

test('only the authenticated users notifications are shown', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    Notification::query()->create([
        'user_id' => $user->getKey(),
        'title' => 'Your own alert',
        'message' => 'Visible to the signed-in account.',
        'type' => NotificationType::System,
    ]);

    Notification::query()->create([
        'user_id' => $otherUser->getKey(),
        'title' => 'Other user alert',
        'message' => 'This should stay hidden.',
        'type' => NotificationType::System,
    ]);

    Livewire::actingAs($user)
        ->test('notifications.notification-bell')
        ->assertSee('Your own alert')
        ->assertDontSee('Other user alert');
});
