<?php

namespace Database\Seeders;

use App\Enums\MarketCategory;
use App\Enums\NotificationType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Enums\VendorStatus;
use App\Enums\VideoCallStatus;
use App\Models\Cart;
use App\Models\Category;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MarketplaceDemoSeeder extends Seeder
{
    public const STABLE_ADMIN_EMAIL = 'admin@example.com';

    public const STABLE_VENDOR_EMAIL = 'vendor@example.com';

    public const STABLE_CUSTOMER_EMAIL = 'test@example.com';

    public const STABLE_VENDOR_STORE_NAME = "Aling Nena's Palengke";

    private ?string $passwordHash = null;

    /**
     * Run the marketplace demo seed.
     */
    public function run(): void
    {
        $summary = DB::transaction(function (): array {
            $categories = $this->seedCategories();
            $stableUsers = $this->seedStableUsers();

            $approvedVendorTarget = fake()->numberBetween(34, 35);
            $pendingVendorTarget = fake()->numberBetween(8, 10);
            $rejectedVendorTarget = fake()->numberBetween(5, 6);
            $additionalCustomerTarget = fake()->numberBetween(150, 165);

            $approvedVendors = collect([
                $this->seedStableVendorProfile($stableUsers['vendor']),
            ])->merge($this->seedApprovedVendors($approvedVendorTarget - 1))->values();

            $pendingVendors = $this->seedVendorApplicants($pendingVendorTarget, VendorStatus::Pending);
            $rejectedVendors = $this->seedVendorApplicants($rejectedVendorTarget, VendorStatus::Rejected);
            $customers = collect([$stableUsers['customer']])
                ->merge($this->seedCustomers($additionalCustomerTarget))
                ->values();

            $products = $this->seedProducts(
                vendors: $approvedVendors,
                categories: $categories,
                targetCount: fake()->numberBetween(950, 1050),
            );

            $cartCount = $this->seedCarts(
                customers: $customers,
                activeProducts: $products->where('status', ProductStatus::Active->value)->values(),
                targetCount: fake()->numberBetween(80, 100),
            );
            $favoriteCount = $this->seedFavorites(
                customers: $customers,
                vendors: $approvedVendors,
                targetCount: fake()->numberBetween(200, 300),
            );

            $orders = $this->seedOrders(
                customers: $customers,
                vendors: $approvedVendors,
                productsByVendor: $products
                    ->where('status', ProductStatus::Active->value)
                    ->groupBy('vendor_id'),
                targetCount: fake()->numberBetween(400, 500),
            );

            $directMessageResult = $this->seedDirectMessages(
                customers: $customers,
                vendors: $approvedVendors,
                orders: $orders,
                targetCount: fake()->numberBetween(1000, 1500),
            );
            $this->seedMessageAttachments(fake()->numberBetween(18, 32));

            $groups = $this->seedConversationGroups(
                users: $customers->merge($this->vendorUsers($approvedVendors))->values(),
                targetCount: fake()->numberBetween(15, 20),
            );
            $groupMessageCount = $this->seedGroupMessages(
                groups: $groups,
                targetCount: fake()->numberBetween(300, 500),
            );
            $this->seedGroupMessageAttachments(fake()->numberBetween(8, 18));

            $notificationCount = $this->seedNotifications(
                orders: $orders,
                messageEvents: $directMessageResult['notification_events'],
                users: collect($stableUsers)->values()
                    ->merge($customers)
                    ->merge($this->vendorUsers($approvedVendors))
                    ->unique('id')
                    ->values(),
                vendors: $approvedVendors,
                products: $products,
                targetCount: fake()->numberBetween(600, 800),
            );

            $reportCount = $this->seedReports(
                customers: $customers,
                vendors: $approvedVendors,
                orders: $orders,
                admin: $stableUsers['admin'],
                targetCount: fake()->numberBetween(30, 50),
            );
            $videoCallCount = $this->seedVideoCalls(
                customers: $customers,
                vendors: $approvedVendors,
                groups: $groups,
                targetCount: fake()->numberBetween(40, 60),
            );
            $nicknameCount = $this->seedUserNicknames(
                customers: $customers,
                vendors: $approvedVendors,
                targetCount: fake()->numberBetween(100, 150),
            );
            $starCount = $this->seedVendorCustomerStars(
                customers: $customers,
                vendors: $approvedVendors,
                targetCount: fake()->numberBetween(80, 120),
            );

            return [
                'users' => 3 + ($approvedVendorTarget - 1) + $pendingVendorTarget + $rejectedVendorTarget + $additionalCustomerTarget,
                'approved_vendors' => $approvedVendors->count(),
                'pending_vendors' => $pendingVendors->count(),
                'rejected_vendors' => $rejectedVendors->count(),
                'products' => $products->count(),
                'carts' => $cartCount,
                'favorites' => $favoriteCount,
                'orders' => $orders->count(),
                'messages' => $directMessageResult['count'],
                'groups' => $groups->count(),
                'group_messages' => $groupMessageCount,
                'notifications' => $notificationCount,
                'reports' => $reportCount,
                'video_calls' => $videoCallCount,
                'nicknames' => $nicknameCount,
                'stars' => $starCount,
            ];
        });

        $this->printSummary($summary);
    }

    /**
     * @return Collection<string, Category>
     */
    public function seedCategories(): Collection
    {
        $categories = collect();

        foreach (MarketCategory::topLevelCases() as $category) {
            $categories->put($category->value, Category::query()->updateOrCreate(
                ['slug' => $category->value],
                [
                    'name' => $category->label(),
                    'parent_id' => null,
                    'description' => $category->description(),
                    'image' => $category->imageUrl(),
                    'created_at' => Carbon::now()->subDays(fake()->numberBetween(30, 90)),
                ],
            ));
        }

        foreach (MarketCategory::leafCases() as $category) {
            $parent = $category->parent();

            $categories->put($category->value, Category::query()->updateOrCreate(
                ['slug' => $category->value],
                [
                    'name' => $category->label(),
                    'parent_id' => $parent === null ? null : $categories->get($parent->value)?->id,
                    'description' => $category->description(),
                    'image' => $category->imageUrl(),
                    'created_at' => Carbon::now()->subDays(fake()->numberBetween(30, 90)),
                ],
            ));
        }

        return $categories;
    }

    /**
     * @return array{admin: User, vendor: User, customer: User}
     */
    private function seedStableUsers(): array
    {
        return [
            'admin' => $this->seedStableUser(
                email: self::STABLE_ADMIN_EMAIL,
                name: 'SukiMarket Tagum Admin',
                role: UserRole::Admin,
                phone: $this->tagumPhone(),
                address: 'SukiMarket Operations, Magugpo Poblacion, Tagum City, Davao del Norte',
            ),
            'vendor' => $this->seedStableUser(
                email: self::STABLE_VENDOR_EMAIL,
                name: 'Aling Nena Santos',
                role: UserRole::Vendor,
                phone: $this->tagumPhone(),
                address: 'Stall 12, Tagum City Public Market, Magugpo Poblacion, Tagum City, Davao del Norte',
            ),
            'customer' => $this->seedStableUser(
                email: self::STABLE_CUSTOMER_EMAIL,
                name: 'Test Customer',
                role: UserRole::Customer,
                phone: $this->tagumPhone(),
                address: $this->tagumAddress(),
            ),
        ];
    }

    private function seedStableUser(string $email, string $name, UserRole $role, string $phone, string $address): User
    {
        return User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'email_verified_at' => Carbon::now()->subDays(fake()->numberBetween(5, 30)),
                'email_verification_code' => null,
                'email_verification_code_expires_at' => null,
                'password' => $this->demoPasswordHash(),
                'role' => $role->value,
                'phone' => $phone,
                'address' => $address,
                'profile_image' => null,
                'is_active' => true,
                'brand_color' => '#059669',
            ],
        );
    }

    private function seedStableVendorProfile(User $vendor): VendorProfile
    {
        return VendorProfile::query()->updateOrCreate(
            ['user_id' => $vendor->id],
            [
                'store_name' => self::STABLE_VENDOR_STORE_NAME,
                'store_description' => 'Palengke-style sariwang gulay, prutas, isda, at karne mula Tagum City Public Market. Kilala si Aling Nena sa maagang stock at tapat na presyo para sa mga suki sa Magugpo.',
                'vendor_address' => 'Stall 12, Tagum City Public Market, Brgy. Magugpo Poblacion, Tagum City, Davao del Norte',
                'store_image' => $this->unsplashUrl('vendors'),
                'status' => VendorStatus::Approved->value,
                'rejection_reason' => null,
                'approved_at' => Carbon::now()->subDays(fake()->numberBetween(20, 80)),
                'created_at' => Carbon::now()->subDays(fake()->numberBetween(30, 90)),
            ],
        )->load('user');
    }

    /**
     * @return Collection<int, VendorProfile>
     */
    private function seedApprovedVendors(int $count): Collection
    {
        $users = $this->seedUsers($count, UserRole::Vendor, 'approved-vendor');
        $storeNames = collect(self::storeNames())
            ->reject(fn (string $name): bool => $name === self::STABLE_VENDOR_STORE_NAME)
            ->shuffle()
            ->values();
        $descriptions = collect(self::storeDescriptions())->shuffle()->values();
        $rows = [];

        foreach ($users->values() as $index => $user) {
            $createdAt = Carbon::now()->subDays(fake()->numberBetween(30, 90))->subHours(fake()->numberBetween(0, 23));
            $storeName = $storeNames->get($index) ?? 'SukiDirect Tagum Stall '.Str::upper(Str::random(4));

            $rows[] = [
                'user_id' => $user->id,
                'store_name' => $storeName,
                'store_description' => $descriptions->get($index % $descriptions->count()),
                'vendor_address' => $this->tagumMarketAddress(),
                'store_image' => $this->unsplashUrl('vendors'),
                'status' => VendorStatus::Approved->value,
                'rejection_reason' => null,
                'approved_at' => $createdAt->copy()->addDays(fake()->numberBetween(1, 7)),
                'created_at' => $createdAt,
            ];
        }

        DB::table('vendor_profiles')->insert($rows);

        return VendorProfile::query()
            ->with('user')
            ->whereIn('user_id', $users->pluck('id'))
            ->get();
    }

    /**
     * @return Collection<int, VendorProfile>
     */
    private function seedVendorApplicants(int $count, VendorStatus $status): Collection
    {
        $users = $this->seedUsers($count, UserRole::Vendor, $status->value.'-vendor');
        $storeNames = collect(self::storeNames())->shuffle()->values();
        $rows = [];

        foreach ($users->values() as $index => $user) {
            $createdAt = Carbon::now()->subDays(fake()->numberBetween(3, 45))->subHours(fake()->numberBetween(0, 23));

            $rows[] = [
                'user_id' => $user->id,
                'store_name' => $storeNames->get(($index + fake()->numberBetween(1, 12)) % $storeNames->count()).' Application',
                'store_description' => $status === VendorStatus::Pending
                    ? fake()->randomElement(self::pendingVendorDescriptions())
                    : fake()->randomElement(self::storeDescriptions()),
                'vendor_address' => $this->tagumMarketAddress(),
                'store_image' => $this->unsplashUrl('vendors'),
                'status' => $status->value,
                'rejection_reason' => $status === VendorStatus::Rejected
                    ? fake()->randomElement(self::rejectionReasons())
                    : null,
                'approved_at' => null,
                'created_at' => $createdAt,
            ];
        }

        DB::table('vendor_profiles')->insert($rows);

        return VendorProfile::query()
            ->with('user')
            ->whereIn('user_id', $users->pluck('id'))
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    private function seedCustomers(int $count): Collection
    {
        return $this->seedUsers($count, UserRole::Customer, 'customer');
    }

    /**
     * @return Collection<int, User>
     */
    private function seedUsers(int $count, UserRole $role, string $emailLabel): Collection
    {
        $rows = [];
        $emails = [];

        for ($i = 0; $i < $count; $i++) {
            $name = $this->filipinoName();
            $email = $this->uniqueDemoEmail($name, $emailLabel);
            $createdAt = Carbon::now()->subDays(fake()->numberBetween(1, 90))->subMinutes(fake()->numberBetween(0, 1440));

            $emails[] = $email;
            $rows[] = [
                'name' => $name,
                'email' => $email,
                'email_verified_at' => $createdAt->copy()->addMinutes(fake()->numberBetween(5, 1440)),
                'email_verification_code' => null,
                'email_verification_code_expires_at' => null,
                'password' => $this->demoPasswordHash(),
                'role' => $role->value,
                'phone' => $this->tagumPhone(),
                'address' => $this->tagumAddress(),
                'profile_image' => null,
                'is_active' => fake()->boolean(96),
                'brand_color' => fake()->randomElement(['#059669', '#047857', '#0f766e', '#16a34a', '#ea580c', '#c2410c']),
                'remember_token' => Str::random(10),
                'created_at' => $createdAt,
                'updated_at' => $createdAt->copy()->addDays(fake()->numberBetween(0, 7)),
            ];
        }

        collect($rows)->chunk(250)->each(fn (Collection $chunk): bool => DB::table('users')->insert($chunk->all()));

        return User::query()
            ->whereIn('email', $emails)
            ->get();
    }

    /**
     * @param  Collection<int, VendorProfile>  $vendors
     * @param  Collection<string, Category>  $categories
     * @return Collection<int, object>
     */
    private function seedProducts(Collection $vendors, Collection $categories, int $targetCount): Collection
    {
        $plans = $this->productPlans($vendors->count(), $targetCount);
        $leafSlugs = collect(MarketCategory::leafValues());
        $rows = [];

        foreach ($vendors->values() as $vendorIndex => $vendor) {
            $plan = $plans[$vendorIndex];

            foreach ([ProductStatus::Active->value => $plan['active'], ProductStatus::Inactive->value => $plan['inactive']] as $status => $count) {
                for ($i = 0; $i < $count; $i++) {
                    $slug = fake()->randomElement($leafSlugs->all());
                    $category = $categories->get($slug);

                    if (! $category instanceof Category) {
                        continue;
                    }

                    $createdAt = Carbon::now()->subDays(fake()->numberBetween(1, 90))->subMinutes(fake()->numberBetween(0, 1440));

                    $rows[] = $this->productRow(
                        vendorId: $vendor->id,
                        categoryId: $category->id,
                        categorySlug: $slug,
                        status: $status,
                        createdAt: $createdAt,
                    );
                }
            }
        }

        collect($rows)->chunk(300)->each(fn (Collection $chunk): bool => DB::table('products')->insert($chunk->all()));

        return DB::table('products')
            ->whereIn('vendor_id', $vendors->pluck('id'))
            ->where('created_at', '>=', Carbon::now()->subDays(91))
            ->get();
    }

    /**
     * @return array<int, array{active: int, inactive: int}>
     */
    private function productPlans(int $vendorCount, int $targetCount): array
    {
        $plans = [];
        $remaining = $targetCount;

        for ($i = 0; $i < $vendorCount; $i++) {
            $vendorsLeft = $vendorCount - $i - 1;
            $minimum = max(23, $remaining - ($vendorsLeft * 36));
            $maximum = min(36, $remaining - ($vendorsLeft * 23));
            $total = fake()->numberBetween($minimum, $maximum);
            $minimumInactive = max(3, $total - 30);
            $maximumInactive = min(6, $total - 20);
            $inactive = fake()->numberBetween($minimumInactive, $maximumInactive);

            $plans[] = [
                'active' => $total - $inactive,
                'inactive' => $inactive,
            ];

            $remaining -= $total;
        }

        return $plans;
    }

    /**
     * @return array<string, mixed>
     */
    private function productRow(int $vendorId, int $categoryId, string $categorySlug, string $status, Carbon $createdAt): array
    {
        $catalog = self::productCatalog()[$categorySlug] ?? self::productCatalog()['fruit-vegetables'];
        $item = fake()->randomElement($catalog['items']);
        $template = fake()->randomElement($catalog['templates']);
        $market = fake()->randomElement(self::marketReferences());
        $barangay = fake()->randomElement(self::barangays());
        $vendorName = fake()->randomElement(self::femaleFirstNames());
        $price = fake()->numberBetween($catalog['price'][0] * 100, $catalog['price'][1] * 100) / 100;
        $name = strtr($template, [
            '{item}' => Str::headline($item),
            '{fish}' => Str::headline($item),
            '{cut}' => Str::headline($item),
            '{name}' => $vendorName,
            '{place}' => $barangay,
        ]);

        return [
            'vendor_id' => $vendorId,
            'category_id' => $categoryId,
            'name' => $name,
            'description' => strtr(fake()->randomElement($catalog['descriptions']), [
                '{item}' => $item,
                '{market}' => $market,
                '{barangay}' => $barangay,
            ]),
            'price' => number_format($price, 2, '.', ''),
            'stock_quantity' => fake()->numberBetween(5, 200),
            'image' => $this->unsplashUrl($catalog['image']),
            'status' => $status,
            'created_at' => $createdAt,
            'updated_at' => $createdAt->copy()->addDays(fake()->numberBetween(0, 14)),
        ];
    }

    /**
     * @param  Collection<int, User>  $customers
     * @param  Collection<int, object>  $activeProducts
     */
    private function seedCarts(Collection $customers, Collection $activeProducts, int $targetCount): int
    {
        $cartCustomers = $customers->shuffle()->take(min($targetCount, $customers->count()));
        $itemRows = [];

        foreach ($cartCustomers as $customer) {
            $createdAt = Carbon::now()->subDays(fake()->numberBetween(1, 30))->subMinutes(fake()->numberBetween(0, 1440));
            $cart = Cart::query()->updateOrCreate(
                ['customer_id' => $customer->id],
                ['created_at' => $createdAt],
            );
            $products = $activeProducts->shuffle()->take(fake()->numberBetween(1, min(5, $activeProducts->count())));

            foreach ($products as $product) {
                $itemRows[] = [
                    'cart_id' => $cart->id,
                    'product_id' => $product->id,
                    'quantity' => fake()->numberBetween(1, 6),
                ];
            }
        }

        collect($itemRows)->chunk(300)->each(fn (Collection $chunk): bool => DB::table('cart_items')->insert($chunk->all()));

        return $cartCustomers->count();
    }

    /**
     * @param  Collection<int, User>  $customers
     * @param  Collection<int, VendorProfile>  $vendors
     */
    private function seedFavorites(Collection $customers, Collection $vendors, int $targetCount): int
    {
        $rows = [];
        $used = [];
        $attempts = 0;

        while (count($rows) < $targetCount && $attempts < $targetCount * 12) {
            $attempts++;
            $customer = $customers->random();
            $vendor = $vendors->random();
            $key = $customer->id.':'.$vendor->id;

            if (isset($used[$key])) {
                continue;
            }

            $used[$key] = true;
            $createdAt = Carbon::now()->subDays(fake()->numberBetween(1, 80))->subMinutes(fake()->numberBetween(0, 1440));

            $rows[] = [
                'customer_id' => $customer->id,
                'vendor_id' => $vendor->id,
                'created_at' => $createdAt,
            ];
        }

        collect($rows)->chunk(300)->each(fn (Collection $chunk): int => DB::table('favorites')->insertOrIgnore($chunk->all()));

        return count($rows);
    }

    /**
     * @param  Collection<int, User>  $customers
     * @param  Collection<int, VendorProfile>  $vendors
     * @param  Collection<int, Collection<int, object>>  $productsByVendor
     * @return Collection<int, object>
     */
    private function seedOrders(Collection $customers, Collection $vendors, Collection $productsByVendor, int $targetCount): Collection
    {
        $orders = collect();

        for ($i = 0; $i < $targetCount; $i++) {
            $customer = $customers->random();
            $vendor = $vendors->random();
            $products = ($productsByVendor->get($vendor->id) ?? collect())->values();

            if ($products->isEmpty()) {
                continue;
            }

            $status = $this->randomOrderStatus();
            $createdAt = $this->orderCreatedAt($status);
            $selectedProducts = $products->shuffle()->take(fake()->numberBetween(1, min(5, $products->count())));
            $items = [];
            $totalCents = 0;

            foreach ($selectedProducts as $product) {
                $quantity = fake()->numberBetween(1, 5);
                $unitPrice = (int) round(((float) $product->price) * 100);
                $totalCents += $unitPrice * $quantity;

                $items[] = [
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => number_format($unitPrice / 100, 2, '.', ''),
                ];
            }

            $estimatedDeliveryAt = $status === OrderStatus::Delivered
                ? $createdAt->copy()->addDays(fake()->numberBetween(1, 3))->setTime(fake()->numberBetween(8, 18), fake()->randomElement([0, 15, 30, 45]))
                : ($status === OrderStatus::Cancelled || $status === OrderStatus::Pending
                    ? null
                    : $createdAt->copy()->addDays(fake()->numberBetween(1, 3)));
            $paymentStatus = $this->paymentStatusFor($status);
            $delayNote = $status !== OrderStatus::Pending && fake()->boolean(20)
                ? fake()->randomElement(self::delayNotes())
                : null;
            $total = number_format($totalCents / 100, 2, '.', '');
            $updatedAt = $status === OrderStatus::Pending
                ? $createdAt
                : $createdAt->copy()->addHours(fake()->numberBetween(1, 72));

            $orderId = DB::table('orders')->insertGetId([
                'customer_id' => $customer->id,
                'vendor_id' => $vendor->id,
                'total_amount' => $total,
                'payment_method' => PaymentMethod::Cod->value,
                'payment_status' => $paymentStatus->value,
                'order_status' => $status->value,
                'delivery_address' => $customer->address ?? $this->tagumAddress(),
                'notes' => fake()->boolean(35) ? fake()->randomElement(self::orderNotes()) : null,
                'estimated_delivery_at' => $estimatedDeliveryAt,
                'delay_note' => $delayNote,
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ]);

            DB::table('order_items')->insert(array_map(
                static fn (array $item): array => ['order_id' => $orderId] + $item,
                $items,
            ));

            DB::table('payments')->insert([
                'order_id' => $orderId,
                'method' => PaymentMethod::Cod->value,
                'reference_number' => null,
                'status' => $paymentStatus->value,
                'amount' => $total,
                'paid_at' => $paymentStatus === PaymentStatus::Paid
                    ? ($estimatedDeliveryAt ?? $createdAt)->copy()->addMinutes(fake()->numberBetween(5, 120))
                    : null,
                'created_at' => $createdAt,
            ]);

            $orders->push((object) [
                'id' => $orderId,
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'vendor_id' => $vendor->id,
                'vendor_user_id' => $vendor->user_id,
                'vendor_store_name' => $vendor->store_name,
                'status' => $status->value,
                'created_at' => $createdAt,
            ]);
        }

        return $orders;
    }

    /**
     * @param  Collection<int, User>  $customers
     * @param  Collection<int, VendorProfile>  $vendors
     * @param  Collection<int, object>  $orders
     * @return array{count: int, notification_events: Collection<int, object>}
     */
    private function seedDirectMessages(Collection $customers, Collection $vendors, Collection $orders, int $targetCount): array
    {
        $threadCount = fake()->numberBetween((int) ceil($targetCount / 15), (int) floor($targetCount / 6));
        $messageCounts = $this->distributeCounts($threadCount, $targetCount, 3, 15);
        $ordersByPair = $orders->groupBy(fn (object $order): string => $order->customer_id.':'.$order->vendor_user_id);
        $rows = [];
        $notificationEvents = collect();

        foreach ($messageCounts as $messageCount) {
            $customer = $customers->random();
            $vendor = $vendors->random();
            $pairOrders = $ordersByPair->get($customer->id.':'.$vendor->user_id, collect());
            $orderId = $pairOrders->isNotEmpty() && fake()->boolean(35) ? $pairOrders->random()->id : null;
            $createdAt = Carbon::now()->subDays(fake()->numberBetween(1, 60))->subMinutes(fake()->numberBetween(0, 1440));

            for ($i = 0; $i < $messageCount; $i++) {
                $fromCustomer = $i % 2 === 0;
                $senderId = $fromCustomer ? $customer->id : $vendor->user_id;
                $receiverId = $fromCustomer ? $vendor->user_id : $customer->id;
                $messageAt = $createdAt->copy()->addMinutes($i * fake()->numberBetween(3, 18));

                $rows[] = [
                    'sender_id' => $senderId,
                    'receiver_id' => $receiverId,
                    'order_id' => $orderId,
                    'content' => $fromCustomer
                        ? fake()->randomElement(self::customerMessages())
                        : fake()->randomElement(self::vendorMessages()),
                    'attachment_path' => null,
                    'attachment_name' => null,
                    'attachment_mime' => null,
                    'attachment_size' => null,
                    'is_read' => fake()->boolean(78),
                    'created_at' => $messageAt,
                ];

                if (! $fromCustomer && $i === 1) {
                    $notificationEvents->push((object) [
                        'user_id' => $customer->id,
                        'sender_name' => $vendor->store_name,
                        'vendor_id' => $vendor->id,
                        'created_at' => $messageAt->copy()->addMinutes(1),
                    ]);
                }
            }
        }

        collect($rows)->chunk(500)->each(fn (Collection $chunk): bool => DB::table('messages')->insert($chunk->all()));

        return [
            'count' => count($rows),
            'notification_events' => $notificationEvents,
        ];
    }

    private function seedMessageAttachments(int $targetCount): int
    {
        $messages = DB::table('messages')
            ->inRandomOrder()
            ->limit($targetCount)
            ->get(['id', 'created_at']);
        $rows = $messages->map(fn (object $message): array => [
            'message_id' => $message->id,
            'path' => 'demo/messages/tagum-order-'.Str::lower(Str::random(8)).'.jpg',
            'name' => fake()->randomElement(['fresh-stock.jpg', 'resibo.jpg', 'delivery-photo.jpg', 'palengke-stall.jpg']),
            'mime' => 'image/jpeg',
            'size' => fake()->numberBetween(80_000, 2_500_000),
            'created_at' => Carbon::parse($message->created_at)->addMinutes(fake()->numberBetween(0, 10)),
        ]);

        if ($rows->isNotEmpty()) {
            DB::table('message_attachments')->insert($rows->all());
        }

        return $rows->count();
    }

    /**
     * @param  Collection<int, User>  $users
     * @return Collection<int, object>
     */
    private function seedConversationGroups(Collection $users, int $targetCount): Collection
    {
        $groups = collect();
        $groupNames = collect(self::groupNames())->shuffle()->values();

        for ($i = 0; $i < $targetCount; $i++) {
            $createdAt = Carbon::now()->subDays(fake()->numberBetween(1, 75))->subMinutes(fake()->numberBetween(0, 1440));
            $members = $users->shuffle()->take(fake()->numberBetween(3, min(8, $users->count())))->values();
            $creator = $members->random();
            $groupId = DB::table('conversation_groups')->insertGetId([
                'name' => $groupNames->get($i) ?? 'Tagum Suki Group '.($i + 1),
                'created_by' => $creator->id,
                'avatar_path' => $this->unsplashUrl('vendors'),
                'created_at' => $createdAt,
                'updated_at' => $createdAt->copy()->addDays(fake()->numberBetween(0, 20)),
            ]);

            DB::table('conversation_group_members')->insert($members->map(fn (User $member): array => [
                'group_id' => $groupId,
                'user_id' => $member->id,
                'role' => $member->id === $creator->id ? 'admin' : 'member',
                'joined_at' => $createdAt->copy()->addMinutes(fake()->numberBetween(0, 90)),
                'last_read_at' => fake()->boolean(75)
                    ? $createdAt->copy()->addDays(fake()->numberBetween(0, 20))
                    : null,
            ])->all());

            $groups->push((object) [
                'id' => $groupId,
                'name' => $groupNames->get($i),
                'member_ids' => $members->pluck('id')->all(),
                'created_at' => $createdAt,
            ]);
        }

        return $groups;
    }

    /**
     * @param  Collection<int, object>  $groups
     */
    private function seedGroupMessages(Collection $groups, int $targetCount): int
    {
        $counts = $this->distributeCounts($groups->count(), $targetCount, 15, 40);
        $rows = [];

        foreach ($groups->values() as $index => $group) {
            $createdAt = Carbon::parse($group->created_at)->addHours(fake()->numberBetween(1, 48));

            for ($i = 0; $i < $counts[$index]; $i++) {
                $rows[] = [
                    'group_id' => $group->id,
                    'sender_id' => fake()->randomElement($group->member_ids),
                    'content' => fake()->randomElement(self::groupMessages()),
                    'created_at' => $createdAt->copy()->addMinutes($i * fake()->numberBetween(5, 25)),
                ];
            }
        }

        collect($rows)->chunk(500)->each(fn (Collection $chunk): bool => DB::table('group_messages')->insert($chunk->all()));

        return count($rows);
    }

    private function seedGroupMessageAttachments(int $targetCount): int
    {
        $messages = DB::table('group_messages')
            ->inRandomOrder()
            ->limit($targetCount)
            ->get(['id', 'created_at']);
        $rows = $messages->map(fn (object $message): array => [
            'group_message_id' => $message->id,
            'path' => 'demo/groups/tagum-market-'.Str::lower(Str::random(8)).'.jpg',
            'name' => fake()->randomElement(['bulk-order.jpg', 'fresh-hipon.jpg', 'price-board.jpg', 'night-market.jpg']),
            'mime' => 'image/jpeg',
            'size' => fake()->numberBetween(100_000, 2_800_000),
            'created_at' => Carbon::parse($message->created_at)->addMinutes(fake()->numberBetween(0, 8)),
        ]);

        if ($rows->isNotEmpty()) {
            DB::table('group_message_attachments')->insert($rows->all());
        }

        return $rows->count();
    }

    /**
     * @param  Collection<int, object>  $orders
     * @param  Collection<int, object>  $messageEvents
     * @param  Collection<int, User>  $users
     * @param  Collection<int, VendorProfile>  $vendors
     * @param  Collection<int, object>  $products
     */
    private function seedNotifications(
        Collection $orders,
        Collection $messageEvents,
        Collection $users,
        Collection $vendors,
        Collection $products,
        int $targetCount,
    ): int {
        $rows = [];

        foreach ($orders as $order) {
            $createdAt = Carbon::parse($order->created_at)->addMinutes(fake()->numberBetween(5, 90));

            $rows[] = [
                'user_id' => $order->customer_id,
                'title' => 'Order #'.$order->id.' update',
                'message' => $this->orderNotificationMessage($order->status, $order->vendor_store_name),
                'type' => NotificationType::OrderUpdate->value,
                'data' => json_encode(['route' => 'orders.show', 'order_id' => $order->id]),
                'is_read' => fake()->boolean(62),
                'created_at' => $createdAt,
            ];
        }

        foreach ($messageEvents->shuffle()->take(max(1, min($messageEvents->count(), 140))) as $event) {
            if (count($rows) >= $targetCount) {
                break;
            }

            $rows[] = [
                'user_id' => $event->user_id,
                'title' => 'Bagong mensahe',
                'message' => 'May bagong mensahe mula kay '.$event->sender_name.'.',
                'type' => NotificationType::Message->value,
                'data' => json_encode(['route' => 'messages.index', 'vendor_id' => $event->vendor_id]),
                'is_read' => fake()->boolean(55),
                'created_at' => $event->created_at,
            ];
        }

        while (count($rows) < $targetCount) {
            $type = fake()->randomElement([NotificationType::System, NotificationType::NewProduct, NotificationType::Message]);
            $user = $users->random();
            $vendor = $vendors->random();
            $product = $products->random();
            $createdAt = Carbon::now()->subDays(fake()->numberBetween(1, 90))->subMinutes(fake()->numberBetween(0, 1440));
            [$title, $message, $data] = match ($type) {
                NotificationType::NewProduct => [
                    'Bagong produkto sa '.$vendor->store_name,
                    fake()->randomElement(self::newProductNotifications()),
                    ['route' => 'shop.products.show', 'vendor_id' => $vendor->id, 'product_id' => $product->id],
                ],
                NotificationType::Message => [
                    'May nag-message sa SukiMarket',
                    'May bagong mensahe mula sa isang suki sa Tagum City.',
                    ['route' => 'messages.index'],
                ],
                default => [
                    fake()->randomElement(['SukiMarket Tagum update', 'Orchid City market alert', 'Palengke reminder']),
                    fake()->randomElement(self::systemNotifications()),
                    ['route' => 'shop.home'],
                ],
            };

            $rows[] = [
                'user_id' => $user->id,
                'title' => $title,
                'message' => strtr($message, [
                    '{store}' => $vendor->store_name,
                    '{product}' => $product->name,
                ]),
                'type' => $type->value,
                'data' => json_encode($data),
                'is_read' => fake()->boolean(58),
                'created_at' => $createdAt,
            ];
        }

        collect($rows)->chunk(500)->each(fn (Collection $chunk): bool => DB::table('notifications')->insert($chunk->all()));

        return count($rows);
    }

    /**
     * @param  Collection<int, User>  $customers
     * @param  Collection<int, VendorProfile>  $vendors
     * @param  Collection<int, object>  $orders
     */
    private function seedReports(Collection $customers, Collection $vendors, Collection $orders, User $admin, int $targetCount): int
    {
        $statuses = collect([ReportStatus::Open, ReportStatus::Reviewed, ReportStatus::Dismissed]);

        while ($statuses->count() < $targetCount) {
            $statuses->push(fake()->randomElement([ReportStatus::Open, ReportStatus::Open, ReportStatus::Reviewed, ReportStatus::Dismissed]));
        }

        $rows = [];

        foreach ($statuses->shuffle()->values() as $status) {
            $customerReport = fake()->boolean(82);
            $order = $orders->isNotEmpty() && fake()->boolean(70) ? $orders->random() : null;
            $vendor = $order === null ? $vendors->random() : $vendors->firstWhere('id', $order->vendor_id);
            $customer = $order === null ? $customers->random() : $customers->firstWhere('id', $order->customer_id);

            if (! $vendor instanceof VendorProfile || ! $customer instanceof User) {
                continue;
            }

            $reviewedAt = $status === ReportStatus::Open
                ? null
                : Carbon::now()->subDays(fake()->numberBetween(1, 30))->subMinutes(fake()->numberBetween(0, 1440));
            $createdAt = $reviewedAt === null
                ? Carbon::now()->subDays(fake()->numberBetween(1, 60))->subMinutes(fake()->numberBetween(0, 1440))
                : $reviewedAt->copy()->subDays(fake()->numberBetween(1, 20));

            $rows[] = [
                'reporter_id' => $customerReport ? $customer->id : $vendor->user_id,
                'reported_user_id' => $customerReport ? $vendor->user_id : $customer->id,
                'order_id' => $order?->id,
                'reporter_role' => $customerReport ? UserRole::Customer->value : UserRole::Vendor->value,
                'reason' => fake()->randomElement(ReportReason::availableFor($customerReport ? 'customer' : 'vendor'))->value,
                'description' => $customerReport
                    ? fake()->randomElement(self::reportDescriptions())
                    : fake()->randomElement(self::vendorReportDescriptions()),
                'attachment_path' => fake()->boolean(18) ? 'demo/reports/report-'.Str::lower(Str::random(8)).'.jpg' : null,
                'status' => $status->value,
                'reviewed_by' => $status === ReportStatus::Open ? null : $admin->id,
                'admin_notes' => $status === ReportStatus::Open ? null : fake()->randomElement(self::reportAdminNotes()),
                'reviewed_at' => $reviewedAt,
                'created_at' => $createdAt,
                'updated_at' => $reviewedAt ?? $createdAt,
            ];
        }

        collect($rows)->chunk(100)->each(fn (Collection $chunk): bool => DB::table('reports')->insert($chunk->all()));

        return count($rows);
    }

    /**
     * @param  Collection<int, User>  $customers
     * @param  Collection<int, VendorProfile>  $vendors
     * @param  Collection<int, object>  $groups
     */
    private function seedVideoCalls(Collection $customers, Collection $vendors, Collection $groups, int $targetCount): int
    {
        $statuses = collect([VideoCallStatus::Ended, VideoCallStatus::Declined, VideoCallStatus::Active, VideoCallStatus::Pending]);

        while ($statuses->count() < $targetCount) {
            $statuses->push(fake()->randomElement([
                VideoCallStatus::Ended,
                VideoCallStatus::Ended,
                VideoCallStatus::Ended,
                VideoCallStatus::Ended,
                VideoCallStatus::Ended,
                VideoCallStatus::Declined,
                VideoCallStatus::Pending,
                VideoCallStatus::Active,
            ]));
        }

        $participants = [];
        $count = 0;

        foreach ($statuses->shuffle()->values() as $status) {
            $isGroupCall = $groups->isNotEmpty() && fake()->boolean(35);
            $createdAt = Carbon::now()->subDays(fake()->numberBetween(1, 45))->subMinutes(fake()->numberBetween(0, 1440));
            $startedAt = in_array($status, [VideoCallStatus::Ended, VideoCallStatus::Active], true)
                ? $createdAt->copy()->addMinutes(fake()->numberBetween(1, 8))
                : null;
            $endedAt = match ($status) {
                VideoCallStatus::Ended => $startedAt?->copy()->addMinutes(fake()->numberBetween(1, 45)),
                VideoCallStatus::Declined => $createdAt->copy()->addMinutes(fake()->numberBetween(1, 6)),
                default => null,
            };

            if ($isGroupCall) {
                $group = $groups->random();
                $callerId = fake()->randomElement($group->member_ids);
                $receiverId = null;
                $groupId = $group->id;
                $conversationKey = 'group-'.$group->id;
                $participantIds = collect($group->member_ids)
                    ->shuffle()
                    ->take(fake()->numberBetween(2, min(5, count($group->member_ids))))
                    ->push($callerId)
                    ->unique()
                    ->values();
            } else {
                $customer = $customers->random();
                $vendor = $vendors->random();
                $callerId = fake()->boolean() ? $customer->id : $vendor->user_id;
                $receiverId = $callerId === $customer->id ? $vendor->user_id : $customer->id;
                $groupId = null;
                $conversationKey = min($callerId, $receiverId).'-'.max($callerId, $receiverId);
                $participantIds = collect([$callerId, $receiverId]);
            }

            $callId = DB::table('video_calls')->insertGetId([
                'caller_id' => $callerId,
                'receiver_id' => $receiverId,
                'group_id' => $groupId,
                'is_group_call' => $isGroupCall,
                'conversation_key' => $conversationKey,
                'status' => $status->value,
                'started_at' => $startedAt,
                'ended_at' => $endedAt,
                'created_at' => $createdAt,
            ]);
            $count++;

            if (in_array($status, [VideoCallStatus::Ended, VideoCallStatus::Active], true) && $startedAt !== null) {
                foreach ($participantIds as $participantId) {
                    $participants[] = [
                        'video_call_id' => $callId,
                        'user_id' => $participantId,
                        'joined_at' => $startedAt->copy()->addSeconds(fake()->numberBetween(0, 20)),
                        'left_at' => $endedAt?->copy()->subSeconds(fake()->numberBetween(0, 20)),
                    ];
                }
            }
        }

        collect($participants)->chunk(200)->each(fn (Collection $chunk): int => DB::table('video_call_participants')->insertOrIgnore($chunk->all()));

        return $count;
    }

    /**
     * @param  Collection<int, User>  $customers
     * @param  Collection<int, VendorProfile>  $vendors
     */
    private function seedUserNicknames(Collection $customers, Collection $vendors, int $targetCount): int
    {
        $rows = [];
        $used = [];
        $attempts = 0;

        while (count($rows) < $targetCount && $attempts < $targetCount * 12) {
            $attempts++;
            $customer = $customers->random();
            $vendor = $vendors->random();
            $vendorOwnsNickname = fake()->boolean();
            $ownerId = $vendorOwnsNickname ? $vendor->user_id : $customer->id;
            $targetId = $vendorOwnsNickname ? $customer->id : $vendor->user_id;
            $key = $ownerId.':'.$targetId;

            if (isset($used[$key]) || $ownerId === $targetId) {
                continue;
            }

            $used[$key] = true;
            $rows[] = [
                'owner_id' => $ownerId,
                'target_id' => $targetId,
                'nickname' => $vendorOwnsNickname
                    ? fake()->randomElement(self::customerNicknames())
                    : fake()->randomElement(self::vendorNicknames()),
                'created_at' => Carbon::now()->subDays(fake()->numberBetween(1, 70)),
                'updated_at' => Carbon::now()->subDays(fake()->numberBetween(0, 30)),
            ];
        }

        collect($rows)->chunk(200)->each(fn (Collection $chunk): int => DB::table('user_nicknames')->insertOrIgnore($chunk->all()));

        return count($rows);
    }

    /**
     * @param  Collection<int, User>  $customers
     * @param  Collection<int, VendorProfile>  $vendors
     */
    private function seedVendorCustomerStars(Collection $customers, Collection $vendors, int $targetCount): int
    {
        $rows = [];
        $used = [];
        $attempts = 0;

        while (count($rows) < $targetCount && $attempts < $targetCount * 12) {
            $attempts++;
            $customer = $customers->random();
            $vendor = $vendors->random();
            $key = $vendor->user_id.':'.$customer->id;

            if (isset($used[$key])) {
                continue;
            }

            $used[$key] = true;
            $rows[] = [
                'vendor_user_id' => $vendor->user_id,
                'customer_id' => $customer->id,
                'created_at' => Carbon::now()->subDays(fake()->numberBetween(1, 80)),
            ];
        }

        collect($rows)->chunk(200)->each(fn (Collection $chunk): int => DB::table('vendor_customer_stars')->insertOrIgnore($chunk->all()));

        return count($rows);
    }

    /**
     * @param  Collection<int, VendorProfile>  $vendors
     * @return Collection<int, User>
     */
    private function vendorUsers(Collection $vendors): Collection
    {
        return User::query()
            ->whereIn('id', $vendors->pluck('user_id'))
            ->get();
    }

    /**
     * @return array<int, int>
     */
    private function distributeCounts(int $bucketCount, int $targetTotal, int $minimum, int $maximum): array
    {
        $counts = [];
        $remaining = $targetTotal;

        for ($i = 0; $i < $bucketCount; $i++) {
            $bucketsLeft = $bucketCount - $i - 1;
            $lowest = max($minimum, $remaining - ($bucketsLeft * $maximum));
            $highest = min($maximum, $remaining - ($bucketsLeft * $minimum));
            $count = fake()->numberBetween($lowest, $highest);

            $counts[] = $count;
            $remaining -= $count;
        }

        return $counts;
    }

    private function randomOrderStatus(): OrderStatus
    {
        return fake()->randomElement([
            OrderStatus::Pending, OrderStatus::Pending, OrderStatus::Pending, OrderStatus::Pending,
            OrderStatus::Confirmed, OrderStatus::Confirmed, OrderStatus::Confirmed,
            OrderStatus::Preparing, OrderStatus::Preparing,
            OrderStatus::Ready, OrderStatus::Ready,
            OrderStatus::Delivered, OrderStatus::Delivered, OrderStatus::Delivered, OrderStatus::Delivered, OrderStatus::Delivered, OrderStatus::Delivered, OrderStatus::Delivered,
            OrderStatus::Cancelled, OrderStatus::Cancelled,
        ]);
    }

    private function orderCreatedAt(OrderStatus $status): Carbon
    {
        if ($status === OrderStatus::Delivered) {
            return Carbon::now()
                ->subDays(fake()->numberBetween(4, 90))
                ->subMinutes(fake()->numberBetween(0, 1440));
        }

        return Carbon::now()
            ->subDays(fake()->numberBetween(1, 90))
            ->subMinutes(fake()->numberBetween(0, 1440));
    }

    private function paymentStatusFor(OrderStatus $status): PaymentStatus
    {
        return match ($status) {
            OrderStatus::Delivered => PaymentStatus::Paid,
            OrderStatus::Cancelled => fake()->boolean() ? PaymentStatus::Failed : PaymentStatus::Pending,
            default => PaymentStatus::Pending,
        };
    }

    private function orderNotificationMessage(string $status, string $storeName): string
    {
        return match ($status) {
            OrderStatus::Pending->value => 'Natanggap na ng '.$storeName.' ang order mo at hinihintay ang kumpirmasyon.',
            OrderStatus::Confirmed->value => 'Kinumpirma na ng '.$storeName.' ang order mo. Ihahanda na ito.',
            OrderStatus::Preparing->value => 'Inihahanda na ang order mo mula '.$storeName.'.',
            OrderStatus::Ready->value => 'Ready na ang order mo sa '.$storeName.'. Hintayin ang delivery update.',
            OrderStatus::Delivered->value => 'Naihatid na ang order mo mula '.$storeName.'. Salamat, suki!',
            default => 'Na-cancel ang order mo mula '.$storeName.'. Makipag-message sa vendor kung may tanong.',
        };
    }

    private function demoPasswordHash(): string
    {
        return $this->passwordHash ??= Hash::make('password');
    }

    private function filipinoName(): string
    {
        $firstName = fake()->boolean()
            ? fake()->randomElement(self::maleFirstNames())
            : fake()->randomElement(self::femaleFirstNames());

        return $firstName.' '.fake()->randomElement(self::surnames());
    }

    private function uniqueDemoEmail(string $name, string $label): string
    {
        return Str::of($name)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '.')
            ->trim('.')
            ->append('.', $label, '.', Str::lower(Str::random(10)), '@tagum.sukimarket.test')
            ->toString();
    }

    private function tagumPhone(): string
    {
        return '+63 9'.fake()->numberBetween(10, 99).' '.fake()->numberBetween(100, 999).' '.fake()->numberBetween(1000, 9999);
    }

    private function tagumAddress(): string
    {
        return fake()->numberBetween(1, 999).' '
            .fake()->randomElement(self::streets()).', Brgy. '
            .fake()->randomElement(self::barangays()).', Tagum City, Davao del Norte';
    }

    private function tagumMarketAddress(): string
    {
        return 'Stall '.fake()->numberBetween(1, 88).', '
            .fake()->randomElement(self::marketReferences()).', '
            .fake()->randomElement(self::barangays()).', Tagum City, Davao del Norte';
    }

    private function unsplashUrl(string $pool): string
    {
        $ids = self::imagePools()[$pool] ?? self::imagePools()['market'];

        return 'https://images.unsplash.com/'.fake()->randomElement($ids).'?w=640&h=640&fit=crop&auto=format';
    }

    /**
     * @param  array<string, int>  $summary
     */
    private function printSummary(array $summary): void
    {
        $this->command?->info('✅ Tagum City SukiMarket seeded!');
        $this->command?->info('📦 Products: '.$summary['products'].' | 👥 Users: '.$summary['users'].' | 🛒 Orders: '.$summary['orders']);
        $this->command?->info('💬 Messages: '.$summary['messages'].' direct / '.$summary['group_messages'].' group | 📢 Notifications: '.$summary['notifications'].' | 🚩 Reports: '.$summary['reports']);
        $this->command?->info('🌺 Vendors: '.$summary['approved_vendors'].' approved, '.$summary['pending_vendors'].' pending, '.$summary['rejected_vendors'].' rejected | 📹 Calls: '.$summary['video_calls']);
    }

    /**
     * @return list<string>
     */
    private static function barangays(): array
    {
        return [
            'Magugpo Poblacion',
            'Canocotan',
            'Liboganon',
            'Pagsabangan',
            'Visayan Village',
            'La Filipina',
            'Cuambogan',
            'Mankilam',
            'Apokon',
            'San Miguel',
            'Hijo',
            'New Balamban',
            'Busaon',
            'Madaum',
            'Magsaysay',
            'Malagamot',
            'Dilawan',
            'Bincungan',
            'Magugpo East',
            'Magugpo North',
            'Magugpo South',
            'Magugpo West',
        ];
    }

    /**
     * @return list<string>
     */
    private static function streets(): array
    {
        return [
            'Purok Rosario',
            'San Nicolas Street',
            'Mabini Avenue',
            'Rizal Street',
            'Quezon Boulevard',
            'MacArthur Highway',
            'National Highway',
            'Orchid Drive',
            'Sampaguita Street',
            'Dao Road',
            'Maharlika Highway',
            'Burgos Street',
        ];
    }

    /**
     * @return list<string>
     */
    private static function marketReferences(): array
    {
        return [
            'Tagum City Public Market',
            'Gaisano Mall Tagum',
            'KCC Mall of Tagum',
            'Tagum City Night Market',
            'Pag-asa Wet Market',
            'NCCC Tagum',
            'Robinsons Place Tagum',
        ];
    }

    /**
     * @return list<string>
     */
    private static function maleFirstNames(): array
    {
        return [
            'Eduardo', 'Ramon', 'Rodel', 'Noel', 'Arnel', 'Roldan', 'Jayson', 'Mark Anthony', 'Christian', 'Ronald',
            'Benedicto', 'Efren', 'Marlon', 'Joel', 'Dennis', 'Alvin', 'Ferdinand', 'Glenn', 'Ronaldo', 'Harvey',
            'Nestor', 'Danilo', 'Wilfredo', 'Renato', 'Jomar', 'Ricky', 'Ace', 'Bong', 'Boyet', 'Nonoy',
            'Manny', 'Jun', 'Dindo', 'Ernie', 'Lito',
        ];
    }

    /**
     * @return list<string>
     */
    private static function femaleFirstNames(): array
    {
        return [
            'Maria', 'Rosario', 'Erlinda', 'Nilda', 'Teresita', 'Lourdes', 'Carmelita', 'Nenita', 'Alma', 'Maricel',
            'Marites', 'Jonalyn', 'Lovely', 'Cristina', 'Anabelle', 'Rowena', 'Girlie', 'Sheryl', 'Analiza', 'Grace',
            'Lenie', 'Rhea', 'Hazel', 'Kathleen', 'Mylene', 'Noemi', 'Virgie', 'Perla', 'Mercy', 'Soledad',
            'Felicidad', 'Luzviminda', 'Marilou', 'Edna', 'Florinda',
        ];
    }

    /**
     * @return list<string>
     */
    private static function surnames(): array
    {
        return [
            'Dela Cruz', 'Santos', 'Reyes', 'Garcia', 'Bautista', 'Mendoza', 'Torres', 'Flores', 'Villanueva', 'Ramos',
            'Castro', 'Morales', 'Aquino', 'Dizon', 'Padilla', 'Soriano', 'Evangelista', 'Manalo', 'Delos Santos', 'Concepcion',
            'Estrada', 'Navarro', 'Hernandez', 'Macaraeg', 'Cayabyab', 'Bacalso', 'Lumabas', 'Galinato', 'Ampongan', 'Tambalo',
            'Balaba', 'Caballero', 'Porcadilla', 'Doronila', 'Lacsamana',
        ];
    }

    /**
     * @return list<string>
     */
    private static function storeNames(): array
    {
        return [
            "Aling Nena's Palengke",
            "Aling Rosa's Gulayan",
            "Aling Cora's Fresh Picks",
            "Aling Belen's Kakanin Corner",
            "Aling Tessie's Seafood Basket",
            "Aling Mercy's Meat Stall",
            "Aling Lorna's Bigasan",
            'Orchid City Fresh Market',
            'Orchid Garden Produce',
            'Orchid City Bangus Express',
            'Orchid City Kakanin House',
            'Davao Norte Seafood Hub',
            'Norte Fresh Picks',
            'DavNorte Fresh',
            'Davao del Norte Organics',
            'Tagum Palengke Direct',
            'Tagum Durian Corner',
            'Tagum Agri Hub',
            'SukiDirect Tagum',
            'Tagum Fresh Produce Updates',
            'Apokon Fresh Catch',
            'Apokon Farm Basket',
            'Canocotan Pork Chop House',
            'Canocotan Chicken Depot',
            'Cuambogan Organic Farm',
            'Mankilam Seafood Stop',
            'Magugpo Rice & Grains',
            'Magugpo Meat Center',
            'Pagsabangan Veggie Lane',
            'Visayan Village Suki Store',
            'Liboganon Tilapia Direct',
            'Madaum Fish Landing',
            'Bincungan Backyard Greens',
            'La Filipina Frozen Goods',
            'Hijo Coastal Catch',
            'New Balamban Bigasan',
            'Sariwang Gulay ni Manang',
            'Lutong-Lutong Karne ni Mang Ben',
            'Tindahan ni Ate Glo',
            'Karnehan ni Mang Rodel',
            'Prutas ni Ate Lovely',
            'Kakanin ni Aling Virgie',
            'Seafood ni Kuya Jun',
            'Palengke Basket ni Manang Perla',
            'NCCC Tagum Fresh Stall',
            'KCC Tagum Market Basket',
            'Gaisano Tagum Produce Lane',
            'Robinsons Tagum Fresh Corner',
            'Pag-asa Wet Market Direct',
            'Night Market Merienda Tagum',
            'Orchid City Suki Basket',
        ];
    }

    /**
     * @return list<string>
     */
    private static function storeDescriptions(): array
    {
        return [
            'A family-run stall bringing fresh produce from Tagum City Public Market to homes around The Orchid City.',
            'Fresh seafood, fish, and shellfish sourced daily from Davao Gulf suppliers and sold palengke-direct in Tagum.',
            'Vegetables harvested from Apokon and Cuambogan farms every morning, packed for suki deliveries across Tagum City.',
            'Local meat cuts, eggs, rice, and pantry staples for carinderias and family kitchens near Magugpo and Mankilam.',
            'Native kakanin and merienda favorites inspired by Tagum City Night Market, cooked in small batches before sunrise.',
            'Davao del Norte fruit, durian, banana, and seasonal harvest sold with honest suki pricing and friendly Taglish service.',
            'Wet-market staples near Pag-asa Wet Market with pre-order support for bulk buyers and barangay group orders.',
            'A Tagum palengke stall serving shoppers from Gaisano Mall Tagum, KCC Mall of Tagum, and nearby barangays.',
            'Farm-to-palengke baskets with leafy greens, aromatics, rice, and frozen goods for weekly family meal planning.',
            'Trusted by Tagum suki buyers for fresh stock, quick replies, and clear prices before delivery.',
        ];
    }

    /**
     * @return list<string>
     */
    private static function pendingVendorDescriptions(): array
    {
        return [
            'Nagtitinda ako ng sariwang gulay mula sa aming taniman sa Apokon, Tagum City. Araw-araw na hinaharvest para masiguro ang freshness. Sample products: pechay ₱35, talong ₱60, sitaw ₱55.',
            'Maliit na seafood stall kami malapit sa Pag-asa Wet Market. May bangus, tilapia, hipon, at pusit depende sa dating ng umaga. Sample prices: bangus ₱160/kilo, hipon ₱280/kilo.',
            'Gumagawa kami ng kakanin para sa Tagum City Night Market. May puto, kutsinta, biko, at suman. Fresh luto tuwing madaling araw.',
            'Backyard poultry at fresh eggs mula sa Canocotan. Gusto naming magbenta ng dressed chicken, itlog, at ready-to-cook cuts sa SukiMarket.',
            'Bigasan at pantry stall sa Magugpo. May regular rice, premium rice, mais, harina, suka, toyo, at mantika para sa araw-araw na lutuan.',
        ];
    }

    /**
     * @return list<string>
     */
    private static function rejectionReasons(): array
    {
        return [
            'Hindi kumpleto ang mga detalye ng tindahan. Mangyaring mag-update ng impormasyon.',
            'Ang mga produkto ay hindi tugma sa kategorya ng marketplace.',
            'Hindi malinaw ang larawan ng tindahan. Mag-upload ng mas malinaw na litrato.',
            'Duplicate na account. May existing na vendor account ka na.',
            'Kulang ang contact details at hindi ma-verify ang stall address sa Tagum City.',
        ];
    }

    /**
     * @return array<string, array{items: list<string>, templates: list<string>, descriptions: list<string>, price: array{0: int, 1: int}, image: string}>
     */
    private static function productCatalog(): array
    {
        $freshDescriptions = [
            'Fresh {item} sourced around {barangay} and checked before listing at {market}.',
            'Suki-quality {item} packed the same day for Tagum City deliveries.',
            'Locally selected {item} with palengke freshness and clear Tagum pricing.',
        ];

        return [
            'leafy-greens' => [
                'items' => ['kangkong', 'pechay', 'malunggay', 'mustasa', 'alugbati', 'camote tops', 'saluyot', 'puso ng saging'],
                'templates' => ['Sariwang {item} mula Tagum', 'Organic {item} - {place} Farm', '{item} Bundle (500g)', 'Premium {item} - Tagum Grown', '{item} - Farm to Palengke'],
                'descriptions' => $freshDescriptions,
                'price' => [15, 120],
                'image' => 'vegetables',
            ],
            'root-crops' => [
                'items' => ['kamote', 'gabi', 'ube', 'singkamas', 'labanos', 'cassava', 'carrots', 'taro'],
                'templates' => ['Sariwang {item} mula Tagum', '{item} (per kilo)', 'Premium {item} - Davao del Norte', '{item} - Farm to Palengke'],
                'descriptions' => $freshDescriptions,
                'price' => [25, 150],
                'image' => 'vegetables',
            ],
            'fruit-vegetables' => [
                'items' => ['sitaw', 'ampalaya', 'talong', 'okra', 'kamatis', 'kalabasa', 'sayote', 'patola', 'upo', 'sili pangsigang'],
                'templates' => ['Sariwang {item} mula Tagum', 'Organic {item} - {place} Farm', '{item} Bundle (500g)', 'Premium {item} - Tagum Grown', '{item} - Farm to Palengke'],
                'descriptions' => $freshDescriptions,
                'price' => [15, 120],
                'image' => 'vegetables',
            ],
            'tropical-fruits' => [
                'items' => ['durian', 'mangga', 'marang', 'rambutan', 'lanzones', 'papaya', 'pineapple', 'mangosteen', 'avocado'],
                'templates' => ['Sweet {item} from Davao del Norte', '{item} - Orchid City Harvest', 'Premium {item} (per kilo)', '{item} mula {place}'],
                'descriptions' => $freshDescriptions,
                'price' => [30, 250],
                'image' => 'fruits',
            ],
            'citrus-fruits' => [
                'items' => ['calamansi', 'dalandan', 'suha', 'lemon', 'dayap'],
                'templates' => ['Fresh {item} Pack', '{item} - Tagum Market Pick', '{item} (per kilo)', 'Juicy {item} from Davao Norte'],
                'descriptions' => $freshDescriptions,
                'price' => [30, 180],
                'image' => 'fruits',
            ],
            'bananas-plantains' => [
                'items' => ['lakatan banana', 'saba banana', 'latundan banana', 'cardaba banana', 'senorita banana'],
                'templates' => ['Sweet {item} Bunch', '{item} - Davao del Norte Grown', '{item} (per kilo)', 'Fresh {item} for Merienda'],
                'descriptions' => $freshDescriptions,
                'price' => [30, 180],
                'image' => 'fruits',
            ],
            'fresh-fish' => [
                'items' => ['bangus', 'tilapia', 'galunggong', 'tambakol', 'tanigue', 'maya-maya', 'alumahan', 'tulingan', 'lapu-lapu', 'espada', 'dalagang bukid', 'hasa-hasa', 'tuna', 'matangbaka'],
                'templates' => ['Sariwa {fish} - Dagat ng Davao', '{fish} (per kilo)', '{fish} - Tagum Fresh Catch', 'Luto-ready {fish}', '{fish} - Cleaned & Dressed'],
                'descriptions' => ['Fresh {item} delivered from Davao Gulf suppliers to {market}.', 'Cleaned {item} ready for sinigang, paksiw, or ihaw in Tagum homes.', 'Palengke-fresh {item} selected early for suki buyers around {barangay}.'],
                'price' => [80, 450],
                'image' => 'seafood',
            ],
            'shellfish' => [
                'items' => ['tahong', 'halaan', 'talaba', 'kuhol', 'diwal'],
                'templates' => ['Fresh {item} (per kilo)', '{item} - Davao Gulf Shellfish', 'Luto-ready {item}', '{item} Pack - Tagum Fresh'],
                'descriptions' => ['Fresh {item} checked for market quality before delivery around Tagum City.', 'Best for sabaw, butter-garlic, or ihaw after pickup from {market}.', 'Packed {item} for suki orders around {barangay}.'],
                'price' => [120, 600],
                'image' => 'seafood',
            ],
            'crustaceans' => [
                'items' => ['hipon', 'alimango', 'alimasag', 'sugpo', 'pasayan'],
                'templates' => ['Fresh {item} - Tagum Seafood', '{item} (per kilo)', '{item} - Cleaned on Request', 'Premium {item} from Davao Gulf'],
                'descriptions' => ['Sweet and fresh {item} from trusted Davao Gulf suppliers.', 'Packed with ice for deliveries from {market} to Tagum barangays.', 'Quality {item} for handa, sinigang, or butter-garlic dishes.'],
                'price' => [120, 600],
                'image' => 'seafood',
            ],
            'pork' => [
                'items' => ['liempo', 'kasim', 'pigue', 'buto-buto', 'pork chop', 'pork belly', 'ground pork', 'lechon kawali cut', 'bagnet cut'],
                'templates' => ['{cut} - Karne ni Mang {name}', '{cut} (per kilo)', '{cut} - Grade A Tagum', '{cut} - Halos-lahat kasama'],
                'descriptions' => ['Fresh-cut {item} from local Tagum meat suppliers, packed for same-day cooking.', 'Market-grade {item} for adobo, sinigang, or ihaw.', 'Selected {item} from {market} with suki-friendly butcher cuts.'],
                'price' => [90, 420],
                'image' => 'meat',
            ],
            'beef' => [
                'items' => ['beef brisket', 'beef bones', 'beef kalitiran', 'sirloin', 'ground beef', 'beef shank'],
                'templates' => ['{cut} - Karne ni Mang {name}', '{cut} (per kilo)', '{cut} - Grade A Tagum', '{cut} for Nilaga'],
                'descriptions' => ['Fresh {item} cut for Tagum family cooking.', 'Good-quality {item} for sabaw, bistek, tapa, or kaldereta.', 'Butcher-selected {item} from {market}.'],
                'price' => [120, 420],
                'image' => 'meat',
            ],
            'chicken' => [
                'items' => ['chicken whole', 'chicken breast', 'chicken thigh', 'chicken wings', 'chicken feet', 'chicken liver'],
                'templates' => ['{cut} - Fresh Poultry Tagum', '{cut} (per kilo)', '{cut} - Luto-ready', '{cut} from {place} Poultry'],
                'descriptions' => ['Fresh poultry cut and packed for tinola, adobo, or fried chicken.', 'Luto-ready {item} from trusted suppliers around Tagum City.', 'Clean {item} for everyday family meals.'],
                'price' => [90, 320],
                'image' => 'meat',
            ],
            'rice' => [
                'items' => ['premium rice', 'regular milled rice', 'dinorado rice', 'well-milled rice', 'brown rice', 'malagkit rice'],
                'templates' => ['{item} - Tagum Bigasan', '{item} (5kg sack)', 'Premium {item} - Davao Norte', '{item} for Daily Meals'],
                'descriptions' => ['Freshly stocked {item} from a Tagum bigasan near {market}.', 'Clean grains packed for family meals and carinderia use.', 'Reliable {item} for weekly pantry refills.'],
                'price' => [45, 280],
                'image' => 'grains',
            ],
            'corn-flour' => [
                'items' => ['corn grits', 'all-purpose flour', 'rice flour', 'glutinous rice flour', 'cornstarch'],
                'templates' => ['{item} Pack', '{item} - Palengke Pantry', '{item} for Kakanin', 'Tagum {item} Refill'],
                'descriptions' => ['Pantry-ready {item} for Tagum kitchens and kakanin makers.', 'Fresh stock from {market}, packed clean for delivery.', 'Budget-friendly {item} for daily cooking.'],
                'price' => [45, 280],
                'image' => 'grains',
            ],
            'eggs' => [
                'items' => ['medium eggs', 'large eggs', 'duck eggs', 'salted eggs', 'quail eggs'],
                'templates' => ['Fresh {item} Tray', '{item} - Canocotan Farm', '{item} Half Tray', '{item} for Suki Breakfast'],
                'descriptions' => ['Fresh {item} delivered from poultry suppliers around Tagum City.', 'Checked {item} for sari-sari stores and family kitchens.', 'Good stock from {market} for breakfast and baking.'],
                'price' => [12, 180],
                'image' => 'dairy',
            ],
            'milk-dairy' => [
                'items' => ['fresh milk', 'evaporated milk', 'cheese slices', 'butter', 'condensed milk'],
                'templates' => ['{item} - Chilled Market Stock', '{item} Pack', '{item} for Merienda', '{item} Pantry Refill'],
                'descriptions' => ['Chilled {item} sourced from Tagum market groceries.', 'Fresh stock for desserts, coffee, and family snacks.', 'Packed carefully for deliveries around {barangay}.'],
                'price' => [35, 180],
                'image' => 'dairy',
            ],
            'fresh-aromatics' => [
                'items' => ['sibuyas', 'bawang', 'luya', 'sili', 'spring onions', 'tanglad'],
                'templates' => ['Fresh {item} Pack', '{item} - Palengke Aromatics', '{item} Bundle', 'Tagum {item} Refill'],
                'descriptions' => ['Fresh {item} for everyday Filipino cooking.', 'Picked from {market} for adobo, tinola, paksiw, and sinigang.', 'Aromatic {item} packed for quick kitchen refills.'],
                'price' => [25, 350],
                'image' => 'spices',
            ],
            'condiments-sauces' => [
                'items' => ['suka', 'toyo', 'patis', 'bagoong', 'banana ketchup', 'sukang tuba', 'soy sauce'],
                'templates' => ['{item} Bottle', '{item} - Tagum Pantry', '{item} for Lutong Bahay', 'Palengke {item} Refill'],
                'descriptions' => ['Pantry-ready {item} for Tagum home cooking.', 'Reliable {item} stock from {market}.', 'Classic Filipino seasoning for everyday meals.'],
                'price' => [25, 350],
                'image' => 'spices',
            ],
            'cooking-oils' => [
                'items' => ['coconut oil', 'vegetable oil', 'palm oil', 'canola oil', 'garlic oil'],
                'templates' => ['{item} Bottle', '{item} - Fry-ready', '{item} Pantry Refill', 'Tagum Market {item}'],
                'descriptions' => ['Cooking-ready {item} for frying, sauteing, and carinderia prep.', 'Fresh grocery stock from {market}.', 'Packed safely for delivery across Tagum City.'],
                'price' => [45, 350],
                'image' => 'spices',
            ],
            'dried-fish' => [
                'items' => ['tuyo', 'daing', 'dilis', 'danggit', 'dried pusit', 'dried espada'],
                'templates' => ['{item} Pack', '{item} - Dried Goods Tagum', 'Crispy {item}', '{item} for Almusal'],
                'descriptions' => ['Salty and savory {item} for classic Filipino breakfast.', 'Dried goods stock from {market}, packed clean for suki buyers.', 'Best with garlic rice, tomato, and suka.'],
                'price' => [35, 280],
                'image' => 'dried',
            ],
            'smoked-fermented-goods' => [
                'items' => ['tinapa', 'bagoong alamang', 'burong isda', 'salted fish', 'smoked bangus'],
                'templates' => ['{item} Pack', '{item} - Palengke Preserved Goods', '{item} from Davao Norte', 'Tagum {item} Special'],
                'descriptions' => ['Flavorful {item} for quick meals and side dishes.', 'Preserved goods selected at {market}.', 'Packed carefully for Tagum suki households.'],
                'price' => [35, 280],
                'image' => 'dried',
            ],
            'steamed-kakanin' => [
                'items' => ['puto', 'kutsinta', 'suman', 'sapin-sapin', 'puto bumbong', 'palitaw'],
                'templates' => ['Homemade {item}', '{item} - Luto ni Aling {name}', '{item} - Tagum Night Market Favorite', '{item} (bilhin sa tindahan)'],
                'descriptions' => ['Freshly cooked {item} inspired by Tagum City Night Market merienda stalls.', 'Soft and sweet {item} prepared in small batches before delivery.', 'Classic kakanin from {barangay}, perfect for pasalubong.'],
                'price' => [20, 180],
                'image' => 'kakanin',
            ],
            'native-delicacies' => [
                'items' => ['biko', 'kalamay', 'bibingka', 'maja blanca', 'cassava cake', 'ube halaya', 'leche flan', 'tibok-tibok'],
                'templates' => ['Homemade {item}', '{item} - Luto ni Aling {name}', '{item} - Tagum Night Market Favorite', '{item} Party Tray'],
                'descriptions' => ['Rich {item} made for suki orders around The Orchid City.', 'Merienda-ready {item} cooked with fresh ingredients from {market}.', 'Sweet native delicacy from {barangay}, packed for sharing.'],
                'price' => [20, 180],
                'image' => 'kakanin',
            ],
            'cured-meats' => [
                'items' => ['longganisa', 'tocino', 'tapa', 'hamonado', 'skinless longganisa', 'chorizo de Tagum'],
                'templates' => ['{item} Pack', '{item} - Frozen Breakfast', 'Tagum {item} Special', '{item} for Almusal'],
                'descriptions' => ['Ready-to-cook {item} for quick Tagum breakfasts.', 'Frozen stock packed safely from {market}.', 'Family-size {item} for garlic rice mornings.'],
                'price' => [65, 380],
                'image' => 'frozen',
            ],
            'frozen-ready-to-cook' => [
                'items' => ['lumpia shanghai', 'siomai', 'fish balls', 'kikiam', 'marinated bangus', 'chicken nuggets', 'embutido'],
                'templates' => ['Frozen {item} Pack', '{item} - Ready to Cook', 'Tagum {item} Party Pack', '{item} for Quick Merienda'],
                'descriptions' => ['Frozen {item} for fast family meals and merienda.', 'Ready-to-cook stock from {market}, packed for same-day delivery.', 'Convenient {item} for busy Tagum households.'],
                'price' => [65, 380],
                'image' => 'frozen',
            ],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private static function imagePools(): array
    {
        return [
            'vegetables' => [
                'photo-1597362925123-77861d3fbac7', 'photo-1543362906-acfc16c67564', 'photo-1610832958506-aa56368176cf',
                'photo-1591115765373-5207764f72e7', 'photo-1512621776951-a57141f2eefd', 'photo-1540420773420-3366772f4999',
                'photo-1563565375-f3fdfdbefa83', 'photo-1576045057995-568f588f82fb',
            ],
            'fruits' => [
                'photo-1560806887-1e4cd0b6cbd6', 'photo-1467811780776-30a0d44e5f24', 'photo-1488459716781-31db52582fe9',
                'photo-1550258987-190a2d41a8ba', 'photo-1474440692490-2e83ae13ba29', 'photo-1519996529931-28324d5a630e',
                'photo-1546548970-71785318a17b', 'photo-1528825871115-3581a5387919',
            ],
            'seafood' => [
                'photo-1519708227418-c8fd9a32b7a2', 'photo-1534483509719-3feaee7c30da', 'photo-1559737558-2f5a35f4523b',
                'photo-1601050690597-df0568f70950', 'photo-1510130387422-82bed34b37e9', 'photo-1580822184713-fc5400e7fe10',
                'photo-1565680018434-b513d5e5fd47',
            ],
            'meat' => [
                'photo-1529692236671-f1f6cf9683ba', 'photo-1588347785102-2944d611eb4c', 'photo-1432139555190-58524dae6a55',
                'photo-1568901346375-23c9450c58cd', 'photo-1607623814075-e51df1bdc82f', 'photo-1602470520998-f4a52199a3d6',
                'photo-1558030006-450675393462', 'photo-1604503468506-a8da13d11d36',
            ],
            'grains' => ['photo-1586201375761-83865001e31c', 'photo-1536304993881-ff6e9eefa2a6', 'photo-1551754655-cd27e38d2076'],
            'dairy' => ['photo-1607863680198-23d4b2565df0', 'photo-1518569656558-1f25e69d2221', 'photo-1550583724-b2692b85b150'],
            'spices' => ['photo-1596040033229-a9821ebd058d', 'photo-1540553016722-983e48a2cd10', 'photo-1563805042-7684c019e1cb', 'photo-1474979266404-7eaacbcd87c5'],
            'dried' => ['photo-1589881133595-a3c085cb731d', 'photo-1504674900247-0877df9cc836', 'photo-1601050690597-df0568f70950'],
            'kakanin' => ['photo-1563805042-7684c019e1cb', 'photo-1578985545062-69928b1d9587', 'photo-1567620905732-2d1ec7ab7445', 'photo-1466637574441-749b8f19452f'],
            'frozen' => ['photo-1584568694244-14fbdf83bd30', 'photo-1585325701165-8a37e04f9ef1', 'photo-1530554764233-e79e16c91d08'],
            'vendors' => [
                'photo-1578916171728-46686eac8d58', 'photo-1488459716781-31db52582fe9', 'photo-1414235077428-338989a2e8c0',
                'photo-1504674900247-0877df9cc836', 'photo-1542838132-92c53300491e', 'photo-1506484381205-f7945653044d',
                'photo-1498579809087-ef1e558fd1da',
            ],
            'market' => ['photo-1542838132-92c53300491e', 'photo-1506484381205-f7945653044d', 'photo-1488459716781-31db52582fe9'],
        ];
    }

    /**
     * @return list<string>
     */
    private static function customerMessages(): array
    {
        return [
            'Ate, may stock pa ba kayo ng bangus ngayon?',
            'Magkano ang kilo ng sitaw ninyo?',
            'Pwede po bang mag-order ng 2 kilo na liempo?',
            'Saan kayo naroroon sa palengke?',
            'Kamusta ang quality ng tilapia nyo ngayon?',
            'Libre ba ang delivery sa loob ng Tagum City?',
            'May pakete ba kayo ng mixed vegetables?',
            'Puwede bang i-reserve ang order ko para bukas?',
            'May discount po ba kapag pang-carinderia ang order?',
            'Pwede po half kilo muna? Titikman ko kung fresh.',
            'Nasa Magugpo South ako, kaya ba delivery mamayang hapon?',
            'Cash on delivery lang po ba talaga ngayon?',
            'Paki-send po ng picture ng bagong dating na hipon.',
            'May durian pa ba galing Davao del Norte?',
            'Pwede pong pauna sa listahan? Suki na po ako.',
        ];
    }

    /**
     * @return list<string>
     */
    private static function vendorMessages(): array
    {
        return [
            'Meron pa po! Sariwa galing kanina lang sa palengke.',
            '₱85 per kilo po. May discount pag 5 kilos up.',
            'Oo po, pwede. Anong oras gusto mong maipadala?',
            'Nasa gitna kami ng palengke, malapit sa tindahan ng pandesal.',
            'Super fresh po! Galing Davao port kaninang umaga.',
            '₱50 lang po delivery fee sa loob ng lungsod.',
            'May veggie pack po kami, ₱180 nalang kasama na lahat.',
            'Sige po! I-message mo lang ng address at contact number.',
            'Cash on delivery lang po kami ngayon.',
            '30-45 minuto po depende sa traffic sa Magugpo.',
            'Salamat ate! Ire-reserve ko na para hindi maubos.',
            'Balik-suki po kayo! Lagi kaming nandito.',
            'May bagong dating po galing Pag-asa Wet Market.',
            'Pwede po pickup sa stall o delivery sa barangay ninyo.',
            'Naantala lang po sa palengke, pero on the way na.',
        ];
    }

    /**
     * @return list<string>
     */
    private static function groupNames(): array
    {
        return [
            'Tagum Market Vendors 🌺',
            'Suki Circle - Apokon Area',
            'Palengke Buyers Group',
            'Magugpo Wet Market Chat',
            'Tagum City Agri Hub',
            'Orchid City Food Network',
            'Davao Norte Suki Club',
            'Barangay Cuambogan Buyers',
            'Tagum Fresh Produce Updates',
            'Night Market Insiders 🌙',
            'Visayan Village Suki',
            'Bulk Orders - Tagum Market',
            'Liboganon Community Market',
            'Kakanin Lovers Tagum',
            'Seafood Direct - Tagum',
            'Mankilam Pantry Circle',
            'Canocotan Meat Buyers',
            'Magugpo South Suki Chat',
            'Pag-asa Wet Market Updates',
            'Orchid City Bulk Suki',
        ];
    }

    /**
     * @return list<string>
     */
    private static function groupMessages(): array
    {
        return [
            'Mga suki, may bagong dating na hipon! ₱220/kilo lang 🦐',
            'Nagmahal na naman ang gulay dahil bagyo sa Bukidnon 😅',
            'Sino may order ng kalamay ngayon? May extra pa ko ✅',
            'Order na kayo bago maubusan! Mabilis mapunta ito 🏃',
            'Salamat sa lahat ng nag-order kahapon! ❤️',
            'Next delivery: Sabado ng umaga, bago mag-alas-otso',
            'Fresh bangus available! 3 kilos nalang natira',
            'May group order ba tayo para libre delivery? 😊',
            'Traffic sa Magugpo ngayon, expect konting delay lang po.',
            'May bagong presyo ang itlog: medium tray ₱205 today.',
            'Kinsa naay extra talong? Need namo pang-carinderia.',
            'Durian from Davao Norte available after lunch 🌺',
            'Paki-lista na lang dito ang bulk orders para isang byahe.',
            'KCC area delivery after 4PM, sabay na mga suki.',
            'Fresh pechay and kangkong from Apokon farm, limited lang.',
        ];
    }

    /**
     * @return list<string>
     */
    private static function newProductNotifications(): array
    {
        return [
            'Bagong stock ng durian mula Davao! Tingnan na sa {store}.',
            '{product} is now available from {store} for Tagum suki buyers.',
            'Fresh listing alert: {product} just landed at {store}.',
            'May bagong paninda sa {store}: {product}.',
        ];
    }

    /**
     * @return list<string>
     */
    private static function systemNotifications(): array
    {
        return [
            'Maligayang pagdating sa SukiMarket Tagum! 🌺',
            'Nag-update na ang presyo ng gulay ngayong linggo.',
            'Flash sale sa mga vendor ngayon!',
            'Tip: mag-message muna sa vendor para sa pinaka-fresh na stock.',
            'Tagum City Night Market picks are moving fast tonight.',
            'COD-only checkout is active for all SukiMarket orders.',
        ];
    }

    /**
     * @return list<string>
     */
    private static function reportDescriptions(): array
    {
        return [
            'Hindi dumating ang order ko kahit 3 oras na nakalipas. Sinabi niya 30 minuto lang.',
            'Ang ibinigay na karne ay parang hindi sariwa. May amoy na.',
            'Sinabi niyang ₱85/kilo pero nang mag-order ako, naging ₱120 na.',
            'Hindi sumasagot sa messages. Hindi ko mahanap ang tindahan niya.',
            'Nag-post ng mga produktong wala naman talagang stock.',
            "Rude ang tindera. Paulit-ulit na nagsabi ng 'wala na' kahit may stock pa.",
            'Nagpadala ng maling item. Pechay ang order ko, repolyo ang dumating.',
            'Kinuha ang pera pero hindi napadala ang order. Scammer ito!',
            'Late na late ang delivery at malamig na ang kakanin pagdating.',
            'Hindi pareho ang timbang ng isda kumpara sa napag-usapan.',
        ];
    }

    /**
     * @return list<string>
     */
    private static function vendorReportDescriptions(): array
    {
        return [
            'Nagpa-reserve ng maraming stock pero hindi sumipot sa usapan.',
            'Paulit-ulit na nanghihingi ng delivery sa maling address.',
            'Nagpadala ng masasakit na salita sa chat matapos maubos ang stock.',
            'Nag-place ng bulk order pero hindi tinanggap ang COD delivery.',
        ];
    }

    /**
     * @return list<string>
     */
    private static function reportAdminNotes(): array
    {
        return [
            'Nakipag-ugnayan na kami sa vendor. Nagbigay ng refund ang vendor.',
            'Verified na malayo ang issue. Dismissed per investigation.',
            'Pinagbabalaan ang vendor. Second offense na ito.',
            'Tinawagan ang parehong parties at naayos na ang order concern.',
            'Evidence reviewed. No further action needed for now.',
        ];
    }

    /**
     * @return list<string>
     */
    private static function delayNotes(): array
    {
        return [
            'Nanuod muna ng basketball',
            'Traffic sa Magugpo',
            'Bagyo sa probinsya',
            'Naubos ang stocks kahapon',
            'Naantala sa palengke',
            'Late dumating ang delivery rider',
            'Nagkaaberya sa timbang ng isda',
            'Hinintay pa ang bagong dating na gulay',
        ];
    }

    /**
     * @return list<string>
     */
    private static function orderNotes(): array
    {
        return [
            'Pakibalot nang maayos, may senior sa bahay.',
            'Pakitawagan muna bago dumating sa gate.',
            'Kung may mas fresh na stock, iyon na lang po.',
            'Paki-extra plastic para hindi tumulo.',
            'For lunch prep po ito, sana before 11AM.',
            'Suki na po ako, baka may konting discount.',
        ];
    }

    /**
     * @return list<string>
     */
    private static function customerNicknames(): array
    {
        return [
            'Suki sa Apokon',
            'Bulk Buyer',
            'Mabait na Suki',
            'Laging COD',
            'Taga Magugpo',
            'Kakanin Regular',
            'Seafood Suki',
            'Early Pickup',
            'Loyal Customer',
            'Palengke Friend',
        ];
    }

    /**
     * @return list<string>
     */
    private static function vendorNicknames(): array
    {
        return [
            'Ate Bangus',
            'Kuya Gulay',
            'Aling Fresh',
            'Kakanin Queen',
            'Meat Supplier',
            'Durian Contact',
            'Bigasan Suki',
            'Seafood Direct',
            'Orchid Vendor',
            'Palengke Bestie',
        ];
    }
}
