<?php

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogger;
use Livewire\Livewire;

test('creates an audit log entry', function (): void {
    $user = User::factory()->create();

    $entry = AuditLogger::log(AuditEvent::UserLoggedIn, 'Test login.', $user, $user->id);

    expect($entry)->toBeInstanceOf(AuditLog::class)
        ->and($entry->event)->toBe(AuditEvent::UserLoggedIn)
        ->and($entry->description)->toBe('Test login.')
        ->and($entry->user_id)->toBe($user->id);
});

test('creates an entry with null user for system events', function (): void {
    $entry = AuditLogger::log(AuditEvent::OrderPlaced, 'System-generated order.', null, null);

    expect($entry->user_id)->toBeNull();
});

test('stores metadata as json', function (): void {
    $entry = AuditLogger::log(AuditEvent::OrderPlaced, 'Order placed.', null, null, ['total' => '₱150.00']);

    expect($entry->metadata)->toBe(['total' => '₱150.00']);
});

test('admin audit log page is accessible to admins', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.audit'))
        ->assertOk();
});

test('admin audit log page is forbidden to customers', function (): void {
    $customer = User::factory()->create();

    $this->actingAs($customer)
        ->get(route('admin.audit'))
        ->assertRedirect();
});

test('filters audit log by event type', function (): void {
    AuditLogger::log(AuditEvent::UserLoggedIn, 'Visible login entry.', null, null);
    AuditLogger::log(AuditEvent::OrderPlaced, 'Hidden order entry.', null, null);

    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test('pages::admin.audit-log')
        ->set('eventFilter', AuditEvent::UserLoggedIn->value)
        ->assertSee('Visible login entry.')
        ->assertDontSee('Hidden order entry.');
});
