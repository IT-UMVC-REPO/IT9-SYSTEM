<?php

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Models\Order;
use App\Models\Report;
use App\Models\User;
use App\Models\VendorProfile;
use Livewire\Livewire;

function createReportScenario(): array
{
    $customer = User::factory()->create();
    $vendorUser = User::factory()->vendor()->create();
    $vendorProfile = VendorProfile::factory()->for($vendorUser, 'user')->approved()->create();
    $order = Order::factory()
        ->for($customer, 'customer')
        ->for($vendorProfile, 'vendor')
        ->create();

    return compact('customer', 'vendorUser', 'vendorProfile', 'order');
}

test('customers can submit a report against a vendor', function () {
    $scenario = createReportScenario();

    Livewire::actingAs($scenario['customer'])
        ->test('report.report-modal', [
            'reportedUserId' => $scenario['vendorUser']->getKey(),
            'reporterRole' => 'customer',
        ])
        ->set('reason', ReportReason::FakeListings->value)
        ->set('description', 'The listing details did not match the actual stall inventory.')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertDispatched('report-submitted');

    $this->assertDatabaseHas('reports', [
        'reporter_id' => $scenario['customer']->getKey(),
        'reported_user_id' => $scenario['vendorUser']->getKey(),
        'reporter_role' => 'customer',
        'reason' => ReportReason::FakeListings->value,
        'status' => ReportStatus::Open->value,
    ]);
});

test('vendors can submit a report against a customer', function () {
    $scenario = createReportScenario();

    Livewire::actingAs($scenario['vendorUser'])
        ->test('report.report-modal', [
            'reportedUserId' => $scenario['customer']->getKey(),
            'reporterRole' => 'vendor',
            'orderId' => $scenario['order']->getKey(),
        ])
        ->set('reason', ReportReason::HarassmentOrAbuse->value)
        ->set('description', 'The customer sent abusive messages after the order was confirmed.')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertDispatched('report-submitted');

    $this->assertDatabaseHas('reports', [
        'reporter_id' => $scenario['vendorUser']->getKey(),
        'reported_user_id' => $scenario['customer']->getKey(),
        'order_id' => $scenario['order']->getKey(),
        'reporter_role' => 'vendor',
        'reason' => ReportReason::HarassmentOrAbuse->value,
        'status' => ReportStatus::Open->value,
    ]);
});

test('duplicate reports within 30 days are blocked', function () {
    $scenario = createReportScenario();

    Report::factory()->open()->create([
        'reporter_id' => $scenario['customer']->getKey(),
        'reported_user_id' => $scenario['vendorUser']->getKey(),
        'reporter_role' => 'customer',
        'reason' => ReportReason::FraudOrScam,
        'created_at' => now()->subDays(10),
    ]);

    Livewire::actingAs($scenario['customer'])
        ->test('report.report-modal', [
            'reportedUserId' => $scenario['vendorUser']->getKey(),
            'reporterRole' => 'customer',
        ])
        ->set('reason', ReportReason::Other->value)
        ->set('description', 'Trying to submit another report too soon.')
        ->call('submit')
        ->assertNotDispatched('report-submitted');

    expect(Report::query()->count())->toBe(1);
});

test('a non customer vendor cannot access the report modal', function () {
    $admin = User::factory()->admin()->create();
    $scenario = createReportScenario();

    Livewire::actingAs($admin)
        ->test('report.report-modal', [
            'reportedUserId' => $scenario['vendorUser']->getKey(),
            'reporterRole' => 'customer',
        ])
        ->assertForbidden();
});

test('admin can see all reports on the reports page', function () {
    $admin = User::factory()->admin()->create();

    $firstScenario = createReportScenario();
    $secondScenario = createReportScenario();

    Report::factory()->open()->create([
        'reporter_id' => $firstScenario['customer']->getKey(),
        'reported_user_id' => $firstScenario['vendorUser']->getKey(),
        'reporter_role' => 'customer',
        'reason' => ReportReason::FakeListings,
    ]);

    Report::factory()->reviewed()->create([
        'reporter_id' => $secondScenario['vendorUser']->getKey(),
        'reported_user_id' => $secondScenario['customer']->getKey(),
        'order_id' => $secondScenario['order']->getKey(),
        'reporter_role' => 'vendor',
        'reason' => ReportReason::HarassmentOrAbuse,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.reports', ['status' => 'all']))
        ->assertOk()
        ->assertSee($firstScenario['customer']->name)
        ->assertSee($firstScenario['vendorUser']->name)
        ->assertSee($secondScenario['vendorUser']->name)
        ->assertSee($secondScenario['customer']->name)
        ->assertSee('Fake listings')
        ->assertSee('Harassment or abuse');
});

test('admin can mark a report as reviewed with notes', function () {
    $admin = User::factory()->admin()->create();
    $scenario = createReportScenario();
    $report = Report::factory()->open()->create([
        'reporter_id' => $scenario['customer']->getKey(),
        'reported_user_id' => $scenario['vendorUser']->getKey(),
        'reporter_role' => 'customer',
    ]);

    Livewire::actingAs($admin)
        ->test('pages::admin.reports')
        ->call('markReviewed', $report->getKey(), 'Checked the order context and logged the decision.');

    expect($report->fresh()->status)->toBe(ReportStatus::Reviewed)
        ->and($report->fresh()->reviewed_by)->toBe($admin->getKey())
        ->and($report->fresh()->admin_notes)->toBe('Checked the order context and logged the decision.')
        ->and($report->fresh()->reviewed_at)->not->toBeNull();
});

test('admin can dismiss a report', function () {
    $admin = User::factory()->admin()->create();
    $scenario = createReportScenario();
    $report = Report::factory()->open()->create([
        'reporter_id' => $scenario['customer']->getKey(),
        'reported_user_id' => $scenario['vendorUser']->getKey(),
        'reporter_role' => 'customer',
    ]);

    Livewire::actingAs($admin)
        ->test('pages::admin.reports')
        ->call('dismiss', $report->getKey());

    expect($report->fresh()->status)->toBe(ReportStatus::Dismissed)
        ->and($report->fresh()->reviewed_by)->toBe($admin->getKey())
        ->and($report->fresh()->reviewed_at)->not->toBeNull();
});

test('non admins are redirected away from admin reports', function () {
    $customer = User::factory()->create();

    $this->actingAs($customer)
        ->get(route('admin.reports'))
        ->assertRedirect(route('customer.dashboard'));
});
