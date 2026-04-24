<?php

namespace Database\Seeders;

use App\Enums\MarketCategory;
use App\Enums\NotificationType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\UserRole;
use App\Enums\VendorStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Favorite;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MarketplaceDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $categories = $this->seedCategories();

            $this->seedStableAdmin();
            $this->seedStableVendor($categories);
            $this->seedStableCustomer();

            $additionalApprovedVendors = $this->seedApprovedVendors();
            $this->seedPendingVendors();
            $this->seedRejectedVendors();
            $this->seedCustomers();
            $this->seedApprovedVendorProducts($additionalApprovedVendors, $categories);

            $customers = $this->customerPool();
            $approvedVendors = $this->approvedVendorPool();
            $activeProducts = $approvedVendors
                ->flatMap(fn (VendorProfile $vendorProfile): Collection => $vendorProfile->products)
                ->filter(fn (Product $product): bool => $product->status === ProductStatus::Active)
                ->values();

            $this->seedCarts($customers, $activeProducts);
            $this->seedFavorites($customers, $approvedVendors);
            $this->seedOrders($customers, $approvedVendors);
            $this->seedExtraNotifications();
        });
    }

    /**
     * @return Collection<int, Category>
     */
    private function seedCategories(): Collection
    {
        $topLevelCategories = collect(MarketCategory::topLevelCases())
            ->mapWithKeys(function (MarketCategory $marketCategory): array {
                $category = Category::query()->updateOrCreate(
                    ['slug' => $marketCategory->value],
                    [
                        'parent_id' => null,
                        'name' => $marketCategory->label(),
                        'description' => $marketCategory->description(),
                        'image' => $marketCategory->imageUrl(),
                    ],
                );

                return [$marketCategory->value => $category];
            });

        return collect(MarketCategory::leafCases())
            ->map(function (MarketCategory $marketCategory) use ($topLevelCategories): Category {
                $parentCategory = $topLevelCategories->get($marketCategory->parent()?->value);

                return Category::query()->updateOrCreate(
                    ['slug' => $marketCategory->value],
                    [
                        'parent_id' => $parentCategory?->id,
                        'name' => $marketCategory->label(),
                        'description' => $marketCategory->description(),
                        'image' => $marketCategory->imageUrl(),
                    ],
                );
            })
            ->values();
    }

    private function seedStableAdmin(): User
    {
        return User::query()->updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Marketplace Admin',
                'password' => 'password',
                'email_verified_at' => now(),
                'role' => UserRole::Admin,
                'phone' => '+63 900 000 0001',
                'address' => 'SukiMarket HQ, Market Avenue, Quezon City',
                'is_active' => true,
            ],
        );
    }

    /**
     * @param  Collection<int, Category>  $categories
     */
    private function seedStableVendor(Collection $categories): VendorProfile
    {
        $vendorUser = User::query()->updateOrCreate(
            ['email' => 'vendor@example.com'],
            [
                'name' => 'Fresh Vendor',
                'password' => 'password',
                'email_verified_at' => now(),
                'role' => UserRole::Vendor,
                'phone' => '+63 900 000 0002',
                'address' => 'Fresh Vendor Stall, Central Market, Pasig',
                'is_active' => true,
            ],
        );

        $vendorProfile = VendorProfile::query()->updateOrCreate(
            ['user_id' => $vendorUser->id],
            [
                'store_name' => 'Fresh Vendor Market',
                'store_description' => 'Daily market goods from an approved vendor.',
                'store_image' => $this->placeholderImage('Fresh Vendor Market'),
                'status' => VendorStatus::Approved,
                'rejection_reason' => null,
                'approved_at' => now(),
            ],
        );

        $this->seedStableVendorProducts($vendorProfile, $categories);

        return $vendorProfile;
    }

    private function seedStableCustomer(): User
    {
        return User::query()->updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => 'password',
                'email_verified_at' => now(),
                'role' => UserRole::Customer,
                'phone' => '+63 900 000 0003',
                'address' => '123 Demo Street, Mandaluyong City',
                'is_active' => true,
            ],
        );
    }

    /**
     * @return Collection<int, VendorProfile>
     */
    private function seedApprovedVendors(): Collection
    {
        return User::factory()
            ->count(14)
            ->vendor()
            ->state(fn (): array => [
                'email' => sprintf('vendor.%s@example.com', Str::lower((string) Str::ulid())),
                'phone' => fake()->phoneNumber(),
                'address' => fake()->address(),
            ])
            ->create()
            ->map(function (User $vendorUser): VendorProfile {
                return VendorProfile::factory()
                    ->approved()
                    ->for($vendorUser, 'user')
                    ->create();
            })
            ->values();
    }

    private function seedPendingVendors(): void
    {
        User::factory()
            ->count(2)
            ->vendor()
            ->state(fn (): array => [
                'email' => sprintf('vendor-pending.%s@example.com', Str::lower((string) Str::ulid())),
                'phone' => fake()->phoneNumber(),
                'address' => fake()->address(),
            ])
            ->create()
            ->each(function (User $vendorUser): void {
                VendorProfile::factory()
                    ->for($vendorUser, 'user')
                    ->create();
            });
    }

    private function seedRejectedVendors(): void
    {
        User::factory()
            ->count(2)
            ->vendor()
            ->state(fn (): array => [
                'email' => sprintf('vendor-rejected.%s@example.com', Str::lower((string) Str::ulid())),
                'phone' => fake()->phoneNumber(),
                'address' => fake()->address(),
            ])
            ->create()
            ->each(function (User $vendorUser): void {
                VendorProfile::factory()
                    ->rejected()
                    ->for($vendorUser, 'user')
                    ->create();
            });
    }

    private function seedCustomers(): void
    {
        User::factory()
            ->count(74)
            ->state(fn (): array => [
                'email' => sprintf('customer.%s@example.com', Str::lower((string) Str::ulid())),
                'phone' => fake()->phoneNumber(),
                'address' => fake()->address(),
            ])
            ->create();
    }

    /**
     * @param  Collection<int, Category>  $categories
     */
    private function seedStableVendorProducts(VendorProfile $vendorProfile, Collection $categories): void
    {
        $productNameTemplates = [
            'Premium %s Selection',
            'Daily %s Bundle',
            '%s Market Best Seller',
        ];

        $categories->values()->each(function (Category $category, int $categoryIndex) use ($productNameTemplates, $vendorProfile): void {
            foreach ($productNameTemplates as $productIndex => $template) {
                $productName = sprintf($template, $category->name);
                $price = round(65 + (($categoryIndex + 1) * 8.5) + (($productIndex + 1) * 5.75), 2);

                Product::query()->updateOrCreate(
                    [
                        'vendor_id' => $vendorProfile->id,
                        'category_id' => $category->id,
                        'name' => $productName,
                    ],
                    [
                        'description' => sprintf(
                            'A reliable %s favorite stocked daily for marketplace shoppers.',
                            Str::lower($category->name),
                        ),
                        'price' => $price,
                        'stock_quantity' => 40 + ($categoryIndex * 4) + ($productIndex * 6),
                        'image' => $this->placeholderImage($productName),
                        'status' => ProductStatus::Active,
                    ],
                );
            }
        });
    }

    /**
     * @param  Collection<int, VendorProfile>  $vendorProfiles
     * @param  Collection<int, Category>  $categories
     */
    private function seedApprovedVendorProducts(Collection $vendorProfiles, Collection $categories): void
    {
        $vendorProfiles->each(function (VendorProfile $vendorProfile) use ($categories): void {
            Product::factory()
                ->count(8)
                ->for($vendorProfile, 'vendor')
                ->active()
                ->state(fn (): array => [
                    'category_id' => $categories->random()->id,
                ])
                ->create();

            Product::factory()
                ->count(2)
                ->for($vendorProfile, 'vendor')
                ->state(fn (): array => [
                    'category_id' => $categories->random()->id,
                    'status' => ProductStatus::Inactive,
                ])
                ->create();
        });
    }

    /**
     * @return Collection<int, User>
     */
    private function customerPool(): Collection
    {
        return User::query()
            ->where('role', UserRole::Customer->value)
            ->whereDoesntHave('vendorProfile')
            ->with('cart')
            ->get();
    }

    /**
     * @return Collection<int, VendorProfile>
     */
    private function approvedVendorPool(): Collection
    {
        return VendorProfile::query()
            ->approved()
            ->with([
                'user',
                'products',
            ])
            ->get();
    }

    /**
     * @param  Collection<int, User>  $customers
     * @param  Collection<int, Product>  $activeProducts
     */
    private function seedCarts(Collection $customers, Collection $activeProducts): void
    {
        $customers
            ->filter(fn (User $customer): bool => $customer->cart === null)
            ->shuffle()
            ->take(30)
            ->each(function (User $customer) use ($activeProducts): void {
                $cart = Cart::factory()
                    ->for($customer, 'customer')
                    ->create();

                $productCount = min(fake()->numberBetween(2, 5), $activeProducts->count());
                $selectedProducts = $activeProducts->shuffle()->take($productCount);

                $selectedProducts->each(function (Product $product) use ($cart): void {
                    CartItem::query()->create([
                        'cart_id' => $cart->id,
                        'product_id' => $product->id,
                        'quantity' => fake()->numberBetween(1, 4),
                    ]);
                });
            });
    }

    /**
     * @param  Collection<int, User>  $customers
     * @param  Collection<int, VendorProfile>  $approvedVendors
     */
    private function seedFavorites(Collection $customers, Collection $approvedVendors): void
    {
        $customers
            ->shuffle()
            ->take(45)
            ->each(function (User $customer) use ($approvedVendors): void {
                $favoriteCount = min(fake()->numberBetween(1, 3), $approvedVendors->count());

                $approvedVendors
                    ->shuffle()
                    ->take($favoriteCount)
                    ->each(function (VendorProfile $vendorProfile) use ($customer): void {
                        Favorite::query()->firstOrCreate([
                            'customer_id' => $customer->id,
                            'vendor_id' => $vendorProfile->id,
                        ]);
                    });
            });
    }

    /**
     * @param  Collection<int, User>  $customers
     * @param  Collection<int, VendorProfile>  $approvedVendors
     */
    private function seedOrders(Collection $customers, Collection $approvedVendors): void
    {
        for ($index = 0; $index < 120; $index++) {
            $customer = $customers->random();
            $vendorProfile = $approvedVendors->random();
            $activeVendorProducts = $vendorProfile->products
                ->filter(fn (Product $product): bool => $product->status === ProductStatus::Active)
                ->values();

            if ($activeVendorProducts->isEmpty()) {
                continue;
            }

            $paymentMethod = $this->randomPaymentMethod();
            $orderStatus = $this->randomOrderStatus();
            $paymentStatus = $this->paymentStatusFor($orderStatus, $paymentMethod);

            $order = Order::factory()
                ->for($customer, 'customer')
                ->for($vendorProfile, 'vendor')
                ->create([
                    'total_amount' => 0,
                    'payment_method' => $paymentMethod,
                    'payment_status' => $paymentStatus,
                    'order_status' => $orderStatus,
                    'delivery_address' => $customer->address ?? fake()->address(),
                    'notes' => fake()->optional(0.6)->sentence(),
                ]);

            $selectedProducts = $activeVendorProducts
                ->shuffle()
                ->take(min(fake()->numberBetween(1, 4), $activeVendorProducts->count()));

            $totalAmountInCents = 0;

            $selectedProducts->each(function (Product $product) use ($order, &$totalAmountInCents): void {
                $quantity = fake()->numberBetween(1, 3);
                $unitPriceInCents = (int) round(((float) $product->price) * 100);

                OrderItem::query()->create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => number_format($unitPriceInCents / 100, 2, '.', ''),
                ]);

                $totalAmountInCents += $unitPriceInCents * $quantity;
            });

            $totalAmount = number_format($totalAmountInCents / 100, 2, '.', '');

            $order->forceFill([
                'total_amount' => $totalAmount,
            ])->save();

            Payment::factory()
                ->for($order)
                ->create([
                    'method' => $paymentMethod,
                    'status' => $paymentStatus,
                    'amount' => $totalAmount,
                    'reference_number' => $this->paymentReferenceFor($paymentMethod, $paymentStatus),
                    'paid_at' => $paymentStatus === PaymentStatus::Paid ? now() : null,
                ]);

            $this->seedOrderMessages($order, $customer, $vendorProfile->user);
            $this->seedOrderNotification($order, $customer);
        }
    }

    private function seedExtraNotifications(): void
    {
        $users = User::query()->get();
        $notificationTypes = [
            NotificationType::System,
            NotificationType::NewProduct,
            NotificationType::Message,
        ];

        for ($index = 0; $index < 25; $index++) {
            $user = $users->random();
            $notificationType = fake()->randomElement($notificationTypes);
            $content = $this->notificationContentFor($notificationType);

            Notification::query()->create([
                'user_id' => $user->id,
                'title' => $content['title'],
                'message' => $content['message'],
                'type' => $notificationType,
                'is_read' => fake()->boolean(25),
            ]);
        }
    }

    private function seedOrderMessages(Order $order, User $customer, User $vendorUser): void
    {
        Message::query()->create([
            'sender_id' => $customer->id,
            'receiver_id' => $vendorUser->id,
            'order_id' => $order->id,
            'content' => fake()->randomElement([
                'Can you please confirm the availability of these items?',
                'Please include the freshest stock available.',
                'I added this order for today\'s delivery window.',
            ]),
        ]);

        if (fake()->boolean()) {
            Message::query()->create([
                'sender_id' => $vendorUser->id,
                'receiver_id' => $customer->id,
                'order_id' => $order->id,
                'content' => fake()->randomElement([
                    'Order received. We are preparing your items now.',
                    'Thanks for ordering. We will update you once it is ready.',
                    'We have your order queued and will message if anything changes.',
                ]),
            ]);
        }
    }

    private function seedOrderNotification(Order $order, User $customer): void
    {
        Notification::query()->create([
            'user_id' => $customer->id,
            'title' => sprintf('Order #%d update', $order->id),
            'message' => match ($order->order_status) {
                OrderStatus::Pending => 'Your order has been placed and is awaiting vendor confirmation.',
                OrderStatus::Confirmed => 'Your order has been confirmed by the vendor.',
                OrderStatus::Preparing => 'Your items are currently being prepared.',
                OrderStatus::Ready => 'Your order is ready for pickup or dispatch.',
                OrderStatus::Delivered => 'Your order has been delivered successfully.',
                OrderStatus::Cancelled => 'Your order was cancelled. Please contact support if needed.',
            },
            'type' => NotificationType::OrderUpdate,
            'is_read' => false,
        ]);
    }

    /**
     * @return array{title: string, message: string}
     */
    private function notificationContentFor(NotificationType $notificationType): array
    {
        return match ($notificationType) {
            NotificationType::System => [
                'title' => 'Marketplace notice',
                'message' => fake()->randomElement([
                    'New promos are now available across selected vendors.',
                    'Your marketplace home feed has been refreshed with new highlights.',
                    'System maintenance is complete and ordering is back to normal.',
                ]),
            ],
            NotificationType::NewProduct => [
                'title' => 'New products available',
                'message' => fake()->randomElement([
                    'A favorite vendor just listed new arrivals for today.',
                    'Fresh inventory has been added in one of your preferred categories.',
                    'New product listings are now live for browsing.',
                ]),
            ],
            NotificationType::Message => [
                'title' => 'New message received',
                'message' => fake()->randomElement([
                    'A vendor sent you an order update.',
                    'You have a new marketplace conversation waiting.',
                    'A recent order has a new message thread update.',
                ]),
            ],
            NotificationType::OrderUpdate => [
                'title' => 'Order update',
                'message' => 'Your recent order has a new status update.',
            ],
        };
    }

    private function paymentStatusFor(OrderStatus $orderStatus, PaymentMethod $paymentMethod): PaymentStatus
    {
        return match ($orderStatus) {
            OrderStatus::Pending => PaymentStatus::Pending,
            OrderStatus::Confirmed, OrderStatus::Preparing => $paymentMethod === PaymentMethod::Cod
                ? PaymentStatus::Pending
                : (fake()->boolean(60) ? PaymentStatus::Paid : PaymentStatus::Pending),
            OrderStatus::Ready => $paymentMethod === PaymentMethod::Cod
                ? PaymentStatus::Pending
                : PaymentStatus::Paid,
            OrderStatus::Delivered => PaymentStatus::Paid,
            OrderStatus::Cancelled => fake()->boolean(70) ? PaymentStatus::Failed : PaymentStatus::Pending,
        };
    }

    private function paymentReferenceFor(PaymentMethod $paymentMethod, PaymentStatus $paymentStatus): ?string
    {
        if ($paymentMethod === PaymentMethod::Cod || $paymentStatus === PaymentStatus::Pending) {
            return null;
        }

        return sprintf('%s-%s', Str::upper($paymentMethod->value), Str::lower((string) Str::ulid()));
    }

    private function placeholderImage(string $label): string
    {
        return sprintf(
            'https://placehold.co/640x640/png?text=%s',
            rawurlencode($label),
        );
    }

    private function randomOrderStatus(): OrderStatus
    {
        /** @var OrderStatus $orderStatus */
        $orderStatus = fake()->randomElement(OrderStatus::cases());

        return $orderStatus;
    }

    private function randomPaymentMethod(): PaymentMethod
    {
        /** @var PaymentMethod $paymentMethod */
        $paymentMethod = fake()->randomElement(PaymentMethod::cases());

        return $paymentMethod;
    }
}
