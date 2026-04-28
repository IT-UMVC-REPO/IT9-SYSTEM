<?php

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Models\Order;
use App\Models\Report;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

test('customers can attach evidence when submitting a report', function () {
    Storage::fake('public');

    $scenario = createReportScenario();
    $attachment = UploadedFile::fake()->create('stall-proof.png', 512, 'image/png');

    Livewire::actingAs($scenario['customer'])
        ->test('report.report-modal', [
            'reportedUserId' => $scenario['vendorUser']->getKey(),
            'reporterRole' => 'customer',
        ])
        ->set('reason', ReportReason::FakeListings->value)
        ->set('attachmentUpload', $attachment)
        ->call('submit')
        ->assertHasNoErrors()
        ->assertDispatched('report-submitted');

    $report = Report::query()->latest('id')->first();

    expect($report)->not->toBeNull()
        ->and($report->attachment_path)->not->toBeNull()
        ->and(Storage::disk('public')->exists($report->attachment_path))->toBeTrue();
});

test('report modal uses body scrolling without the old inner clipping classes', function () {
    $modalView = file_get_contents(resource_path('views/components/report/⚡report-modal.blade.php'));

    expect($modalView)
        ->toContain('scroll="body"')
        ->not->toContain('max-h-[85vh]')
        ->not->toContain('overflow-y-auto');
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

    $firstReport = Report::factory()->open()->create([
        'reporter_id' => $firstScenario['customer']->getKey(),
        'reported_user_id' => $firstScenario['vendorUser']->getKey(),
        'reporter_role' => 'customer',
        'reason' => ReportReason::FakeListings,
    ]);

    $secondReport = Report::factory()->reviewed()->create([
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
        ->assertSee('Harassment or abuse')
        ->assertSee(route('admin.reports.show', $firstReport), false)
        ->assertSee(route('admin.reports.show', $secondReport), false);
});

test('admin can view a report detail page', function () {
    $admin = User::factory()->admin()->create();
    $scenario = createReportScenario();
    $report = Report::factory()->open()->create([
        'reporter_id' => $scenario['customer']->getKey(),
        'reported_user_id' => $scenario['vendorUser']->getKey(),
        'reporter_role' => 'customer',
        'reason' => ReportReason::FakeListings,
        'description' => 'The item advertised was not what arrived at pickup.',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.reports.show', $report))
        ->assertOk()
        ->assertSee($scenario['customer']->name)
        ->assertSee($scenario['vendorUser']->name)
        ->assertSee('Fake listings')
        ->assertSee('The item advertised was not what arrived at pickup.');
});

test('report detail route requires authentication', function () {
    $report = Report::factory()->open()->create();

    $this->get(route('admin.reports.show', $report))
        ->assertRedirect(route('login'));
});

test('non admins are redirected away from report detail', function () {
    $customer = User::factory()->create();
    $report = Report::factory()->open()->create();

    $this->actingAs($customer)
        ->get(route('admin.reports.show', $report))
        ->assertRedirect(route('customer.dashboard'));
});

test('missing reports return a 404 on the detail page', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.reports.show', 999999))
        ->assertNotFound();
});

test('admin can mark a report as reviewed with notes from the detail page', function () {
    $admin = User::factory()->admin()->create();
    $scenario = createReportScenario();
    $report = Report::factory()->open()->create([
        'reporter_id' => $scenario['customer']->getKey(),
        'reported_user_id' => $scenario['vendorUser']->getKey(),
        'reporter_role' => 'customer',
    ]);

    Livewire::actingAs($admin)
        ->test('pages::admin.report-detail', ['report' => $report])
        ->set('reviewAdminNotes', 'Checked the order context and logged the decision.')
        ->call('markReviewed')
        ->assertRedirect(route('admin.reports'));

    expect($report->fresh()->status)->toBe(ReportStatus::Reviewed)
        ->and($report->fresh()->reviewed_by)->toBe($admin->getKey())
        ->and($report->fresh()->admin_notes)->toBe('Checked the order context and logged the decision.')
        ->and($report->fresh()->reviewed_at)->not->toBeNull();
});

test('admin can dismiss a report from the detail page', function () {
    $admin = User::factory()->admin()->create();
    $scenario = createReportScenario();
    $report = Report::factory()->open()->create([
        'reporter_id' => $scenario['customer']->getKey(),
        'reported_user_id' => $scenario['vendorUser']->getKey(),
        'reporter_role' => 'customer',
    ]);

    Livewire::actingAs($admin)
        ->test('pages::admin.report-detail', ['report' => $report])
        ->call('dismiss')
        ->assertRedirect(route('admin.reports'));

    expect($report->fresh()->status)->toBe(ReportStatus::Dismissed)
        ->and($report->fresh()->reviewed_by)->toBe($admin->getKey())
        ->and($report->fresh()->reviewed_at)->not->toBeNull();
});

test('admin can re-open a handled report from the detail page', function () {
    $admin = User::factory()->admin()->create();
    $scenario = createReportScenario();
    $report = Report::factory()->reviewed()->create([
        'reporter_id' => $scenario['customer']->getKey(),
        'reported_user_id' => $scenario['vendorUser']->getKey(),
        'reporter_role' => 'customer',
    ]);

    Livewire::actingAs($admin)
        ->test('pages::admin.report-detail', ['report' => $report])
        ->call('reopen')
        ->assertRedirect(route('admin.reports'));

    expect($report->fresh()->status)->toBe(ReportStatus::Open)
        ->and($report->fresh()->reviewed_by)->toBeNull()
        ->and($report->fresh()->reviewed_at)->toBeNull();
});

test('non admins are redirected away from admin reports', function () {
    $customer = User::factory()->create();

    $this->actingAs($customer)
        ->get(route('admin.reports'))
        ->assertRedirect(route('customer.dashboard'));
});
