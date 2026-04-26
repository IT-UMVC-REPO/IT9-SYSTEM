<?php

test('completed portal routes no longer rely on legacy placeholder views', function () {
    expect(file_exists(resource_path('views/pages/customer/dashboard.blade.php')))->toBeFalse();
    expect(file_exists(resource_path('views/pages/shop/favorites.blade.php')))->toBeFalse();
    expect(file_exists(resource_path('views/pages/shop/vendors.blade.php')))->toBeFalse();
    expect(file_exists(resource_path('views/pages/messages/inbox.blade.php')))->toBeFalse();
    expect(file_exists(resource_path('views/pages/messages/conversation.blade.php')))->toBeFalse();
    expect(file_exists(resource_path('views/pages/vendor/dashboard.blade.php')))->toBeFalse();
    expect(file_exists(resource_path('views/pages/vendor/orders.blade.php')))->toBeFalse();
    expect(file_exists(resource_path('views/pages/vendor/order-detail.blade.php')))->toBeFalse();
    expect(file_exists(resource_path('views/pages/vendor/sales.blade.php')))->toBeFalse();
    expect(file_exists(resource_path('views/pages/admin/orders.blade.php')))->toBeFalse();
});
