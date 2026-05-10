<?php

use App\Enums\ProductStatus;
use App\Enums\ProductUnit;
use App\Models\Product;
use App\Models\User;
use App\Models\VendorProfile;
use Livewire\Livewire;

function stocksPageVendor(): array
{
    $vendorUser = User::factory()->vendor()->create();
    $vendorProfile = VendorProfile::factory()->for($vendorUser, 'user')->approved()->create();

    return [$vendorUser, $vendorProfile];
}

function stocksPageProduct(VendorProfile $vendorProfile, array $attributes = []): Product
{
    return Product::factory()
        ->for($vendorProfile, 'vendor')
        ->create([
            'unit' => ProductUnit::Kilogram,
            ...$attributes,
        ]);
}

test('vendor can visit the stocks page', function () {
    [$vendorUser] = stocksPageVendor();

    $this->actingAs($vendorUser)
        ->get(route('vendor.stocks'))
        ->assertOk()
        ->assertSee('Stock Management')
        ->assertSee('No products found')
        ->assertSee('brand-panel px-6 py-14 text-center', false)
        ->assertDontSee('brand-panel suki-reveal px-6 py-14 text-center', false);
});

test('non vendor is redirected away', function () {
    $customer = User::factory()->create();

    $this->actingAs($customer)
        ->get(route('vendor.stocks'))
        ->assertRedirect(route('customer.dashboard'));
});

test('stocks page shows correct kpi counts', function () {
    [$vendorUser, $vendorProfile] = stocksPageVendor();

    stocksPageProduct($vendorProfile, ['status' => ProductStatus::Active, 'stock_quantity' => 50]);
    stocksPageProduct($vendorProfile, ['status' => ProductStatus::Active, 'stock_quantity' => 5]);
    stocksPageProduct($vendorProfile, ['status' => ProductStatus::Inactive, 'stock_quantity' => 0]);

    $component = Livewire::actingAs($vendorUser)->test('pages::vendor.stocks');

    expect($component->instance()->stockSummary)->toMatchArray([
        'total' => 3,
        'active' => 2,
        'inactive' => 1,
        'out_of_stock' => 1,
        'low_stock' => 1,
        'total_units' => 55,
    ]);
});

test('filtering by out status shows only out of stock products', function () {
    [$vendorUser, $vendorProfile] = stocksPageVendor();

    $out = stocksPageProduct($vendorProfile, ['name' => 'Sold Out Bangus', 'stock_quantity' => 0]);
    $available = stocksPageProduct($vendorProfile, ['name' => 'Available Bangus', 'stock_quantity' => 12]);

    Livewire::actingAs($vendorUser)
        ->test('pages::vendor.stocks')
        ->set('statusFilter', 'out')
        ->assertSee($out->name)
        ->assertDontSee($available->name);
});

test('filtering by low status respects the low stock threshold', function () {
    [$vendorUser, $vendorProfile] = stocksPageVendor();

    $low = stocksPageProduct($vendorProfile, ['name' => 'Low Pechay', 'status' => ProductStatus::Active, 'stock_quantity' => 8]);
    $high = stocksPageProduct($vendorProfile, ['name' => 'High Pechay', 'status' => ProductStatus::Active, 'stock_quantity' => 15]);

    Livewire::actingAs($vendorUser)
        ->test('pages::vendor.stocks')
        ->set('lowStockThreshold', 10)
        ->set('statusFilter', 'low')
        ->assertSee($low->name)
        ->assertDontSee($high->name);
});

test('search filters products by name', function () {
    [$vendorUser, $vendorProfile] = stocksPageVendor();

    $matching = stocksPageProduct($vendorProfile, ['name' => 'Searchable Mango']);
    $other = stocksPageProduct($vendorProfile, ['name' => 'Hidden Salmon']);

    Livewire::actingAs($vendorUser)
        ->test('pages::vendor.stocks')
        ->set('search', 'Mango')
        ->assertSee($matching->name)
        ->assertDontSee($other->name);
});

test('inline edit set mode saves the correct stock quantity', function () {
    [$vendorUser, $vendorProfile] = stocksPageVendor();
    $product = stocksPageProduct($vendorProfile, ['stock_quantity' => 5]);

    Livewire::actingAs($vendorUser)
        ->test('pages::vendor.stocks')
        ->call('startInlineEdit', $product->getKey())
        ->set("inlineEdits.{$product->id}.quantity", '12')
        ->call('saveInlineEdit', $product->getKey())
        ->assertHasNoErrors();

    expect($product->fresh()->stock_quantity)->toBe(12);
});

test('inline edit add mode increments correctly', function () {
    [$vendorUser, $vendorProfile] = stocksPageVendor();
    $product = stocksPageProduct($vendorProfile, ['stock_quantity' => 5]);

    Livewire::actingAs($vendorUser)
        ->test('pages::vendor.stocks')
        ->call('startInlineEdit', $product->getKey())
        ->set("inlineEdits.{$product->id}.mode", 'add')
        ->set("inlineEdits.{$product->id}.quantity", '7')
        ->call('saveInlineEdit', $product->getKey());

    expect($product->fresh()->stock_quantity)->toBe(12);
});

test('inline edit subtract mode decrements and does not go below zero', function () {
    [$vendorUser, $vendorProfile] = stocksPageVendor();
    $product = stocksPageProduct($vendorProfile, ['stock_quantity' => 5]);

    Livewire::actingAs($vendorUser)
        ->test('pages::vendor.stocks')
        ->call('startInlineEdit', $product->getKey())
        ->set("inlineEdits.{$product->id}.mode", 'subtract')
        ->set("inlineEdits.{$product->id}.quantity", '10')
        ->call('saveInlineEdit', $product->getKey());

    expect($product->fresh()->stock_quantity)->toBe(0);
});

test('inline edit subtract mode warns when the preview will be capped at zero', function () {
    [$vendorUser, $vendorProfile] = stocksPageVendor();
    $product = stocksPageProduct($vendorProfile, ['stock_quantity' => 5]);

    Livewire::actingAs($vendorUser)
        ->test('pages::vendor.stocks')
        ->call('startInlineEdit', $product->getKey())
        ->set("inlineEdits.{$product->id}.mode", 'subtract')
        ->set("inlineEdits.{$product->id}.quantity", '10')
        ->assertSee('This would empty the stock - result will be capped at 0.');
});

test('inline edit validates quantity is numeric and non negative', function () {
    [$vendorUser, $vendorProfile] = stocksPageVendor();
    $product = stocksPageProduct($vendorProfile, ['stock_quantity' => 5]);

    Livewire::actingAs($vendorUser)
        ->test('pages::vendor.stocks')
        ->call('startInlineEdit', $product->getKey())
        ->set("inlineEdits.{$product->id}.quantity", 'abc')
        ->call('saveInlineEdit', $product->getKey())
        ->assertHasErrors(["inlineEdits.{$product->id}.quantity" => 'numeric'])
        ->assertSee('Quantity must be a number.')
        ->set("inlineEdits.{$product->id}.quantity", '-1')
        ->call('saveInlineEdit', $product->getKey())
        ->assertHasErrors(["inlineEdits.{$product->id}.quantity" => 'min'])
        ->assertSee('Quantity cannot go below zero.');
});

test('quick adjust up increments by one', function () {
    [$vendorUser, $vendorProfile] = stocksPageVendor();
    $product = stocksPageProduct($vendorProfile, ['stock_quantity' => 5]);

    Livewire::actingAs($vendorUser)
        ->test('pages::vendor.stocks')
        ->call('quickAdjust', $product->getKey(), 'up');

    expect($product->fresh()->stock_quantity)->toBe(6);
});

test('quick adjust down decrements by one and stops at zero', function () {
    [$vendorUser, $vendorProfile] = stocksPageVendor();
    $product = stocksPageProduct($vendorProfile, ['stock_quantity' => 0]);

    Livewire::actingAs($vendorUser)
        ->test('pages::vendor.stocks')
        ->call('quickAdjust', $product->getKey(), 'down');

    expect($product->fresh()->stock_quantity)->toBe(0);
});

test('toggle status flips active to inactive and vice versa', function () {
    [$vendorUser, $vendorProfile] = stocksPageVendor();
    $product = stocksPageProduct($vendorProfile, ['status' => ProductStatus::Active]);

    Livewire::actingAs($vendorUser)
        ->test('pages::vendor.stocks')
        ->call('toggleStatus', $product->getKey());

    expect($product->fresh()->status)->toBe(ProductStatus::Inactive);

    Livewire::actingAs($vendorUser)
        ->test('pages::vendor.stocks')
        ->call('toggleStatus', $product->getKey());

    expect($product->fresh()->status)->toBe(ProductStatus::Active);
});

test('bulk set action applies to all selected products', function () {
    [$vendorUser, $vendorProfile] = stocksPageVendor();
    $first = stocksPageProduct($vendorProfile, ['stock_quantity' => 1]);
    $second = stocksPageProduct($vendorProfile, ['stock_quantity' => 2]);

    Livewire::actingAs($vendorUser)
        ->test('pages::vendor.stocks')
        ->set('selectedIds', [$first->id, $second->id])
        ->call('openBulkModal', 'set')
        ->set('bulkQuantity', '20')
        ->call('executeBulkAction');

    expect($first->fresh()->stock_quantity)->toBe(20);
    expect($second->fresh()->stock_quantity)->toBe(20);
});

test('bulk add action applies delta to all selected products', function () {
    [$vendorUser, $vendorProfile] = stocksPageVendor();
    $first = stocksPageProduct($vendorProfile, ['stock_quantity' => 1]);
    $second = stocksPageProduct($vendorProfile, ['stock_quantity' => 2]);

    Livewire::actingAs($vendorUser)
        ->test('pages::vendor.stocks')
        ->set('selectedIds', [$first->id, $second->id])
        ->call('openBulkModal', 'add')
        ->set('bulkQuantity', '5')
        ->call('executeBulkAction');

    expect($first->fresh()->stock_quantity)->toBe(6);
    expect($second->fresh()->stock_quantity)->toBe(7);
});

test('bulk subtract action does not produce negative stock', function () {
    [$vendorUser, $vendorProfile] = stocksPageVendor();
    $first = stocksPageProduct($vendorProfile, ['stock_quantity' => 1]);
    $second = stocksPageProduct($vendorProfile, ['stock_quantity' => 8]);

    Livewire::actingAs($vendorUser)
        ->test('pages::vendor.stocks')
        ->set('selectedIds', [$first->id, $second->id])
        ->call('openBulkModal', 'subtract')
        ->set('bulkQuantity', '5')
        ->call('executeBulkAction');

    expect($first->fresh()->stock_quantity)->toBe(0);
    expect($second->fresh()->stock_quantity)->toBe(3);
});

test('bulk activate makes all selected products active', function () {
    [$vendorUser, $vendorProfile] = stocksPageVendor();
    $first = stocksPageProduct($vendorProfile, ['status' => ProductStatus::Inactive]);
    $second = stocksPageProduct($vendorProfile, ['status' => ProductStatus::Inactive]);

    Livewire::actingAs($vendorUser)
        ->test('pages::vendor.stocks')
        ->set('selectedIds', [$first->id, $second->id])
        ->call('openBulkModal', 'activate')
        ->call('executeBulkAction');

    expect($first->fresh()->status)->toBe(ProductStatus::Active);
    expect($second->fresh()->status)->toBe(ProductStatus::Active);
});

test('bulk deactivate makes all selected products inactive', function () {
    [$vendorUser, $vendorProfile] = stocksPageVendor();
    $first = stocksPageProduct($vendorProfile, ['status' => ProductStatus::Active]);
    $second = stocksPageProduct($vendorProfile, ['status' => ProductStatus::Active]);

    Livewire::actingAs($vendorUser)
        ->test('pages::vendor.stocks')
        ->set('selectedIds', [$first->id, $second->id])
        ->call('openBulkModal', 'deactivate')
        ->call('executeBulkAction');

    expect($first->fresh()->status)->toBe(ProductStatus::Inactive);
    expect($second->fresh()->status)->toBe(ProductStatus::Inactive);
});

test('bulk action requires quantity for stock mutations', function () {
    [$vendorUser, $vendorProfile] = stocksPageVendor();
    $product = stocksPageProduct($vendorProfile);

    Livewire::actingAs($vendorUser)
        ->test('pages::vendor.stocks')
        ->set('selectedIds', [$product->id])
        ->call('openBulkModal', 'add')
        ->call('executeBulkAction')
        ->assertHasErrors(['bulkQuantity' => 'required']);
});

test('vendor cannot edit products belonging to another vendor', function () {
    [$vendorUser] = stocksPageVendor();
    [, $otherVendorProfile] = stocksPageVendor();
    $otherProduct = stocksPageProduct($otherVendorProfile);

    Livewire::actingAs($vendorUser)
        ->test('pages::vendor.stocks')
        ->call('startInlineEdit', $otherProduct->getKey())
        ->assertForbidden();
});

test('update threshold changes the low stock threshold value', function () {
    [$vendorUser] = stocksPageVendor();

    Livewire::actingAs($vendorUser)
        ->test('pages::vendor.stocks')
        ->set('lowStockThreshold', 25)
        ->call('updateThreshold')
        ->assertSet('lowStockThreshold', 25)
        ->assertHasNoErrors();
});

test('update threshold rejects values less than one', function () {
    [$vendorUser] = stocksPageVendor();

    Livewire::actingAs($vendorUser)
        ->test('pages::vendor.stocks')
        ->set('lowStockThreshold', 0)
        ->call('updateThreshold')
        ->assertHasErrors(['lowStockThreshold' => 'min']);
});
