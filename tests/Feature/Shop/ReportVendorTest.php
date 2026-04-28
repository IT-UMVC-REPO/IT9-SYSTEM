<?php

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\User;
use App\Models\VendorProfile;
use Livewire\Livewire;

function createReportableVendor(): array
{
    $customer = User::factory()->create();
    $vendorUser = User::factory()->vendor()->create();
    $vendorProfile = VendorProfile::factory()->for($vendorUser, 'user')->approved()->create([
        'store_name' => 'Suki Greens',
    ]);

    return compact('customer', 'vendorUser', 'vendorProfile');
}

function createVendorReporterScenario(): array
{
    $reporterVendorUser = User::factory()->vendor()->create();
    VendorProfile::factory()->for($reporterVendorUser, 'user')->approved()->create();

    $reportedVendorUser = User::factory()->vendor()->create();
    $reportedVendorProfile = VendorProfile::factory()->for($reportedVendorUser, 'user')->approved()->create([
        'store_name' => 'Fresh Valley Greens',
    ]);

    return compact('reporterVendorUser', 'reportedVendorUser', 'reportedVendorProfile');
}

test('report modal renders on vendor detail page for authenticated customers', function () {
    $scenario = createReportableVendor();

    $this->actingAs($scenario['customer'])
        ->get(route('shop.vendors.show', $scenario['vendorProfile']))
        ->assertOk()
        ->assertSee('Report this vendor')
        ->assertSeeLivewire('report.report-modal');
});

test('report modal renders on vendor detail page for authenticated vendors', function () {
    $scenario = createVendorReporterScenario();

    $this->actingAs($scenario['reporterVendorUser'])
        ->get(route('shop.vendors.show', $scenario['reportedVendorProfile']))
        ->assertOk()
        ->assertSee('Report this vendor')
        ->assertSeeLivewire('report.report-modal');
});

test('submitting a valid reason and description creates an open report record', function () {
    $scenario = createReportableVendor();

    Livewire::actingAs($scenario['customer'])
        ->test('report.report-modal', [
            'reportedUserId' => $scenario['vendorUser']->getKey(),
            'reporterRole' => 'customer',
        ])
        ->set('reason', ReportReason::FraudOrScam->value)
        ->set('description', 'The stall asked for payment outside the platform flow.')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertDispatched('report-submitted');

    $this->assertDatabaseHas('reports', [
        'reporter_id' => $scenario['customer']->getKey(),
        'reported_user_id' => $scenario['vendorUser']->getKey(),
        'reason' => ReportReason::FraudOrScam->value,
        'status' => ReportStatus::Open->value,
    ]);
});

test('vendor can submit a report against another vendor', function () {
    $scenario = createVendorReporterScenario();

    Livewire::actingAs($scenario['reporterVendorUser'])
        ->test('report.report-modal', [
            'reportedUserId' => $scenario['reportedVendorUser']->getKey(),
            'reporterRole' => 'vendor',
        ])
        ->set('reason', ReportReason::HarassmentOrAbuse->value)
        ->set('description', 'Repeated abusive messages were sent in marketplace chat.')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertDispatched('report-submitted');

    $this->assertDatabaseHas('reports', [
        'reporter_id' => $scenario['reporterVendorUser']->getKey(),
        'reported_user_id' => $scenario['reportedVendorUser']->getKey(),
        'reason' => ReportReason::HarassmentOrAbuse->value,
        'status' => ReportStatus::Open->value,
    ]);
});

test('selecting other with no description still submits', function () {
    $scenario = createReportableVendor();

    Livewire::actingAs($scenario['customer'])
        ->test('report.report-modal', [
            'reportedUserId' => $scenario['vendorUser']->getKey(),
            'reporterRole' => 'customer',
        ])
        ->set('reason', ReportReason::Other->value)
        ->set('description', '')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertDispatched('report-submitted');

    $report = Report::query()->latest('id')->first();

    expect($report)->not->toBeNull()
        ->and($report->reason)->toBe(ReportReason::Other)
        ->and($report->description)->toBeNull();
});

test('reason options shown to customers only include reasons available to customers', function () {
    $scenario = createReportableVendor();

    $component = Livewire::actingAs($scenario['customer'])
        ->test('report.report-modal', [
            'reportedUserId' => $scenario['vendorUser']->getKey(),
            'reporterRole' => 'customer',
        ])
        ->assertSee(ReportReason::FakeListings->label())
        ->assertSee(ReportReason::InaccurateInfo->label());

    foreach (ReportReason::cases() as $reason) {
        if (! in_array('customer', $reason->availableTo(), true)) {
            $component->assertDontSee($reason->label());
        }
    }
});
