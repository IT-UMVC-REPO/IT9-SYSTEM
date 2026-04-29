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
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MarketplaceDemoSeeder extends Seeder
{
    public const STABLE_ADMIN_EMAIL = 'admin@example.com';

    public const STABLE_VENDOR_EMAIL = 'vendor@example.com';

    public const STABLE_CUSTOMER_EMAIL = 'test@example.com';

    public const STABLE_VENDOR_STORE_NAME = 'Fresh Vendor Market';

    public const ADDITIONAL_APPROVED_VENDOR_COUNT = 14;

    public const PENDING_VENDOR_COUNT = 2;

    public const REJECTED_VENDOR_COUNT = 2;

    public const ADDITIONAL_CUSTOMER_COUNT = 74;

    public const APPROVED_VENDOR_ACTIVE_PRODUCT_COUNT = 8;

    public const APPROVED_VENDOR_INACTIVE_PRODUCT_COUNT = 2;

    public const CART_COUNT = 30;

    public const FAVORITED_CUSTOMER_COUNT = 45;

    public const ORDER_COUNT = 120;

    public const EXTRA_NOTIFICATION_COUNT = 25;

    /**
     * @var array<int, string>
     */
    private const STABLE_VENDOR_PRODUCT_TEMPLATES = [
        'Premium %s Selection',
        'Daily %s Bundle',
        '%s Market Best Seller',
    ];

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
            ['email' => self::STABLE_ADMIN_EMAIL],
            $this->stableUserAttributes(
                name: 'Marketplace Admin',
                role: UserRole::Admin,
                phone: '+63 900 000 0001',
                address: 'SukiMarket HQ, Market Avenue, Quezon City',
            ),
        );
    }

    /**
     * @param  Collection<int, Category>  $categories
     */
    private function seedStableVendor(Collection $categories): VendorProfile
    {
        $vendorUser = User::query()->updateOrCreate(
            ['email' => self::STABLE_VENDOR_EMAIL],
            $this->stableUserAttributes(
                name: 'Fresh Vendor',
                role: UserRole::Vendor,
                phone: '+63 900 000 0002',
                address: 'Fresh Vendor Stall, Central Market, Pasig',
            ),
        );

        $vendorProfile = VendorProfile::query()->updateOrCreate(
            ['user_id' => $vendorUser->id],
            [
                'store_name' => self::STABLE_VENDOR_STORE_NAME,
                'store_description' => 'Fresh produce, seafood, and daily essentials, sourced and stocked every morning for the neighborhood.',
                'store_image' => $this->marketImage('vendor'),
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
            ['email' => self::STABLE_CUSTOMER_EMAIL],
            $this->stableUserAttributes(
                name: 'Test User',
                role: UserRole::Customer,
                phone: '+63 900 000 0003',
                address: '123 Demo Street, Mandaluyong City',
            ),
        );
    }

    /**
     * @return Collection<int, VendorProfile>
     */
    private function seedApprovedVendors(): Collection
    {
        return $this->seedGeneratedVendors(
            count: self::ADDITIONAL_APPROVED_VENDOR_COUNT,
            emailPrefix: 'vendor',
            createVendorProfile: fn (User $vendorUser): VendorProfile => VendorProfile::factory()
                ->approved()
                ->for($vendorUser, 'user')
                ->create(),
        );
    }

    private function seedPendingVendors(): void
    {
        $this->seedGeneratedVendors(
            count: self::PENDING_VENDOR_COUNT,
            emailPrefix: 'vendor-pending',
            createVendorProfile: fn (User $vendorUser): VendorProfile => VendorProfile::factory()
                ->for($vendorUser, 'user')
                ->create(),
        );
    }

    private function seedRejectedVendors(): void
    {
        $this->seedGeneratedVendors(
            count: self::REJECTED_VENDOR_COUNT,
            emailPrefix: 'vendor-rejected',
            createVendorProfile: fn (User $vendorUser): VendorProfile => VendorProfile::factory()
                ->rejected()
                ->for($vendorUser, 'user')
                ->create(),
        );
    }

    private function seedCustomers(): void
    {
        User::factory()
            ->count(self::ADDITIONAL_CUSTOMER_COUNT)
            ->state($this->generatedMarketplaceUserState('customer'))
            ->create();
    }

    /**
     * @param  Collection<int, Category>  $categories
     */
    private function seedStableVendorProducts(VendorProfile $vendorProfile, Collection $categories): void
    {
        $categories->values()->each(function (Category $category, int $categoryIndex) use ($vendorProfile): void {
            foreach (self::STABLE_VENDOR_PRODUCT_TEMPLATES as $productIndex => $template) {
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
                        'image' => $this->marketImage(Str::lower($category->name)),
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
                ->count(self::APPROVED_VENDOR_ACTIVE_PRODUCT_COUNT)
                ->for($vendorProfile, 'vendor')
                ->active()
                ->state(fn (): array => [
                    'category_id' => $categories->random()->id,
                ])
                ->create();

            Product::factory()
                ->count(self::APPROVED_VENDOR_INACTIVE_PRODUCT_COUNT)
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
        if ($activeProducts->isEmpty()) {
            return;
        }

        $customers
            ->filter(fn (User $customer): bool => $customer->cart === null)
            ->shuffle()
            ->take(self::CART_COUNT)
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
        if ($approvedVendors->isEmpty()) {
            return;
        }

        $customers
            ->shuffle()
            ->take(self::FAVORITED_CUSTOMER_COUNT)
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
        if ($customers->isEmpty() || $approvedVendors->isEmpty()) {
            return;
        }

        for ($index = 0; $index < self::ORDER_COUNT; $index++) {
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

        if ($users->isEmpty()) {
            return;
        }

        $notificationTypes = [
            NotificationType::System,
            NotificationType::NewProduct,
            NotificationType::Message,
        ];

        for ($index = 0; $index < self::EXTRA_NOTIFICATION_COUNT; $index++) {
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

    /**
     * @param  callable(User): VendorProfile  $createVendorProfile
     * @return Collection<int, VendorProfile>
     */
    private function seedGeneratedVendors(
        int $count,
        string $emailPrefix,
        callable $createVendorProfile,
    ): Collection {
        return User::factory()
            ->count($count)
            ->vendor()
            ->state($this->generatedMarketplaceUserState($emailPrefix))
            ->create()
            ->map(fn (User $vendorUser): VendorProfile => $createVendorProfile($vendorUser))
            ->values();
    }

    /**
     * @return \Closure(): array{email: string, phone: string, address: string}
     */
    private function generatedMarketplaceUserState(string $emailPrefix): \Closure
    {
        return fn (): array => [
            'email' => sprintf('%s.%s@example.com', $emailPrefix, Str::lower((string) Str::ulid())),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
        ];
    }

    /**
     * @return array{name: string, password: string, email_verified_at: Carbon, role: UserRole, phone: string, address: string, is_active: bool}
     */
    private function stableUserAttributes(
        string $name,
        UserRole $role,
        string $phone,
        string $address,
    ): array {
        return [
            'name' => $name,
            'password' => 'password',
            'email_verified_at' => now(),
            'role' => $role,
            'phone' => $phone,
            'address' => $address,
            'is_active' => true,
        ];
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

    private function marketImage(string $keyword): string
    {
        $photoIds = [
            'vegetables' => 'photo-1540420773420-3366772f4999',
            'leafy' => 'photo-1576045057995-568f588f82fb',
            'seafood' => 'photo-1510130387422-82bed34b37e9',
            'meat' => 'photo-1607623814075-e51df1bdc82f',
            'fruit' => 'photo-1519996529931-28324d5a630e',
            'rice' => 'photo-1536304993881-ff6e9eefa2a6',
            'eggs' => 'photo-1518569656558-1f25e69d2221',
            'dairy' => 'photo-1607863680198-23d4b2565df0',
            'spices' => 'photo-1596040033229-a9821ebd058d',
            'dried' => 'photo-1589881133595-a3c085cb731d',
            'frozen' => 'photo-1584568694244-14fbdf83bd30',
            'sweets' => 'photo-1563805042-7684c019e1cb',
            'vendor' => 'photo-1555939594-58d7cb561ad1',
            'market' => 'photo-1555939594-58d7cb561ad1',
        ];

        $normalizedKeyword = Str::lower($keyword);

        foreach ($photoIds as $needle => $photoId) {
            if (Str::contains($normalizedKeyword, $needle)) {
                return sprintf(
                    'https://images.unsplash.com/%s?w=640&h=640&fit=crop&auto=format',
                    $photoId,
                );
            }
        }

        return 'https://images.unsplash.com/photo-1555939594-58d7cb561ad1?w=640&h=640&fit=crop&auto=format';
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
