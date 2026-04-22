# SukiMarket — Project Dev Workflow

> **Stack:** PHP 8.5 · Laravel 13 · Livewire 4 · Flux UI 2 · Fortify · Spatie Permission · Pest 4 · MySQL · Tailwind CSS v4

---

## Table of Contents

1. [Team Roles](#1-team-roles)
2. [Branch & Git Strategy](#2-branch--git-strategy)
3. [Local Environment Setup](#3-local-environment-setup)
4. [Project Status — What's Done & What's Left](#4-project-status--whats-done--whats-left)
5. [Module Assignments](#5-module-assignments)
6. [Sprint Plan](#6-sprint-plan)
7. [How to Build Any Feature (Standard Order)](#7-how-to-build-any-feature-standard-order)
8. [Code Conventions Cheatsheet](#8-code-conventions-cheatsheet)
9. [Testing Rules](#9-testing-rules)
10. [Pull Request Checklist](#10-pull-request-checklist)
11. [Database & Seeding](#11-database--seeding)
12. [Deployment Checklist](#12-deployment-checklist)

---

## 1. Team Roles

| Person | Responsibility |
|---|---|
| **Trisha Mae Llano** | ??? |
| **Kryztel Kate Valdez** | ??? |
| **Joshua Miguel Moran** | ??? |

> **Lead rule:** ?? reviews and merges all PRs into `develop`. No one merges their own PR.

---

## 2. Branch & Git Strategy

### Branch naming

```
main          ← production-ready only (never push directly)
develop       ← integration branch (all PRs target here)
feature/      ← new features
fix/          ← bug fixes
test/         ← test-only changes
```

**Examples:**
```bash
feature/vendor-product-listings       # ???
feature/customer-cart                 # ???
feature/admin-vendor-approval         # ???
fix/order-status-redirect             # anyone
test/messaging-feature                # anyone
```

### Day-to-day workflow

```bash
# 1. Always start from a fresh develop
git checkout develop
git pull origin develop

# 2. Create your feature branch
git checkout -b feature/my-feature-name

# 3. Work and commit often with descriptive messages
git add .
git commit -m "feat(vendor): add product listing CRUD with image upload"

# 4. Push and open a PR → targeting develop
git push origin feature/my-feature-name
```

### Commit message format

```
feat(module): short description          ← new feature
fix(module): short description           ← bug fix
test(module): short description          ← tests only
refactor(module): short description      ← no behavior change
style(module): short description         ← formatting / UI only
```

**Modules:** `auth` `admin` `vendor` `customer` `cart` `orders` `messaging` `favorites` `notifications`

### Merge rules

- Every PR must have **at least 1 passing CI run** before merge
- Every PR must be **reviewed by one other team member**
- Squash merge into `develop` to keep history clean
- Delete the feature branch after merging

---

## 3. Local Environment Setup

### First-time setup (run once)

```bash
# Clone and enter project
git clone <repo-url> sukimarket
cd sukimarket

# Install PHP dependencies (requires Flux license)
composer config http-basic.composer.fluxui.dev YOUR_FLUX_USERNAME YOUR_FLUX_LICENSE_KEY
composer install

# Install JS dependencies
npm install

# Copy and configure environment
cp .env.example .env
php artisan key:generate

# Create and migrate the database (MySQL)
# Make sure DB_DATABASE=sukimarket exists in MySQL first
php artisan migrate
php artisan db:seed

# Build assets
npm run build
```

### `.env` values to confirm locally

```env
APP_NAME=SukiMarket
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_DATABASE=sukimarket
DB_USERNAME=root
DB_PASSWORD=

FILESYSTEM_DISK=public   # for product/store image uploads
```

### Running the dev server

```bash
composer dev
# This runs concurrently: php artisan serve + queue:listen + npm run dev
```

### Seeded accounts (after `db:seed`)

| Email | Password | Role |
|---|---|---|
| admin@example.com | password | Admin |
| vendor@example.com | password | Approved Vendor |
| test@example.com | password | Customer |

---

## 4. Project Status — What's Done & What's Left

### Already Built

| Area | What's complete |
|---|---|
| **Auth** | Registration, Login, Logout, Password Reset, Email Verification, 2FA, Role-based redirects |
| **Middleware** | `EnsureUserHasRole` with effective role logic (pending/rejected vendor → customer) |
| **Models** | All 12 models: User, VendorProfile, Category, Product, Cart, CartItem, Order, OrderItem, Payment, Message, Notification, Favorite |
| **Migrations** | All tables fully migrated with correct enum columns and FK constraints |
| **Factories** | All models have factories with state methods (approved, rejected, active, completed, etc.) |
| **Customer Storefront** | Browse products, category filter, keyword search, product detail page |
| **Landing Page** | Full marketing landing with live vendor showcase |
| **Settings** | Profile update, password update, 2FA setup, account deletion |
| **Layouts** | App header layout (role-aware nav), Auth layout (simple/card/split), Dashboard placeholder |
| **Tests** | Auth suite, settings suite, storefront suite, marketplace foundation suite |

###  Still Needs to Be Built

| Module | Owner | Status |
|---|---|---|
| **Live Search** | ??? | Not started |
| **Vendor Registration Flow** | ??? | Not started |
| **Vendor Product CRUD** | ??? | Not started |
| **Vendor Order Management** | ??? | Not started |
| **Vendor Sales Summary** | ??? | Not started |
| **Admin Vendor Approval** | ??? | Not started |
| **Admin User Management** | ??? | Not started |
| **Admin Order Oversight** | ??? | Not started |
| **Customer Cart & Checkout** | ??? | Not started |
| **Customer Order Tracking** | ??? | Not started |
| **Favorites (Suki System)** | ??? | Not started |
| **In-App Messaging** | ??? | Not started |
| **Notifications** | ??? / ??? | Not started |
| **Image Upload (Storage)** | ??? (leads) | Not started |

---

## 5. Module Assignments

### ??? — Admin Panel

**Files to create:**
```
routes/web.php                              ← extend admin route group
app/Http/Controllers/AdminController.php   ← extend with new actions
resources/views/pages/admin/
  ⚡vendors.blade.php                       ← vendor list + approval queue
  ⚡vendor-detail.blade.php                 ← single vendor review page
  ⚡users.blade.php                         ← user list with search
  ⚡orders.blade.php                         ← all-orders oversight
tests/Feature/Admin/
  VendorApprovalTest.php
  UserManagementTest.php
  AdminOrderOversightTest.php
```

**Key logic to implement:**
- List all `VendorProfile` records grouped by status (pending / approved / rejected)
- Approve action: set `status = approved`, set `approved_at = now()`, update user `role = vendor`
- Reject action: set `status = rejected`, save `rejection_reason` (required textarea)
- Send a `Notification` to the vendor user on approval or rejection
- Paginated user table with role filter and `is_active` toggle
- Read-only order list across all vendors, filterable by `order_status`

---

### ??? — Vendor Dashboard

**Files to create:**
```
resources/views/pages/vendor/
  ⚡products.blade.php           ← paginated product list
  ⚡product-create.blade.php     ← create product form
  ⚡product-edit.blade.php       ← edit product form
  ⚡orders.blade.php             ← incoming orders list
  ⚡order-detail.blade.php       ← single order with status update
  ⚡sales.blade.php              ← revenue summary table
  ⚡registration.blade.php       ← vendor onboarding form (public route)
tests/Feature/Vendor/
  ProductManagementTest.php
  OrderProcessingTest.php
  VendorRegistrationTest.php
  SalesSummaryTest.php
```

**Key logic to implement:**
- Vendor registration: create `VendorProfile` for an authenticated customer, switch role to `vendor`, profile status starts as `pending`
- Product CRUD scoped to `auth()->user()->vendorProfile`  —  never allow cross-vendor access
- Image upload via `Livewire\WithFileUploads` stored on `public` disk (`storage/app/public/products/`)
- Order list scoped to vendor: show customer name, items, total, `order_status` badge
- Status progression: `pending → confirmed → preparing → ready → delivered` (vendor-controlled)
- Sales summary: group completed orders by week/month, sum `total_amount`

---

### ??? — Customer Features

**Files to create:**
```
resources/views/pages/shop/
  ⚡cart.blade.php               ← cart contents + quantity editing
  ⚡checkout.blade.php           ← address, payment method, notes
  ⚡orders.blade.php             ← order history list
  ⚡order-detail.blade.php       ← single order status + items
  ⚡favorites.blade.php          ← followed vendor storefronts
resources/views/pages/messages/
  ⚡inbox.blade.php              ← conversation list
  ⚡conversation.blade.php       ← chat thread by order or vendor
tests/Feature/Customer/
  CartTest.php
  CheckoutTest.php
  OrderTrackingTest.php
  FavoritesTest.php
  MessagingTest.php
```

**Key logic to implement:**
- Cart: one `Cart` per customer (create on first add), `CartItem` per product, update quantity, remove item, enforce single-vendor-per-cart rule
- Checkout: capture `delivery_address`, `payment_method` (COD / GCash / Maya), `notes` → create `Order` + `OrderItems` + `Payment` → clear cart
- Favorites: toggle `Favorite` record (customer ↔ vendor), show followed vendor listings on favorites page
- Messaging: conversation grouped by `(sender_id, receiver_id, order_id)` — poll for new messages with Livewire `wire:poll`
- Notifications: mark as read on view, unread badge in nav

---

## 6. Sprint Plan

> Each sprint is approximately one week. Adjust to your actual deadline.

### Sprint 1 — Vendor Foundation
**Goal:** Vendor can register, submit a profile, and be reviewed by admin.

| Task | Owner | Branch |
|---|---|---|
| Vendor registration form + `VendorProfile` creation | ??? | `feature/vendor-registration` |
| Admin vendor list + approval/rejection actions | ??? | `feature/admin-vendor-approval` |
| Storage link setup + image upload helper | ??? | `feature/image-upload` |
| Notification on approval / rejection | ??? | `feature/vendor-notifications` |
| Tests for vendor registration + admin approval | ??? + ??? | in respective branches |

**Done definition:** A new user can submit a vendor registration. Admin can approve or reject it. Vendor is redirected to the vendor dashboard only after approval.

---

### Sprint 2 — Vendor Products & Customer Cart
**Goal:** Vendor can list products. Customer can browse and add to cart.

| Task | Owner | Branch |
|---|---|---|
| Vendor product CRUD (list, create, edit, delete) | ??? | `feature/vendor-products` |
| Product image upload with preview | ??? | in above branch |
| Customer cart (add, update qty, remove, view) | ??? | `feature/customer-cart` |
| Single-vendor cart enforcement | ??? | in above branch |
| Tests for product CRUD + cart operations | ??? + ??? | in respective branches |

**Done definition:** Vendor can manage products visible to customers. Customer can build a cart from one vendor at a time.

---

### Sprint 3 — Checkout & Order Lifecycle
**Goal:** Customer can place orders. Vendor can process them.

| Task | Owner | Branch |
|---|---|---|
| Checkout page (address, payment method, notes) | ??? | `feature/checkout` |
| Order + OrderItem + Payment creation on submit | ??? | in above branch |
| Vendor order list + order detail page | ??? | `feature/vendor-orders` |
| Vendor order status updates (confirmed → delivered) | ??? | in above branch |
| Customer order history + order detail page | ??? | `feature/customer-orders` |
| Tests for checkout flow + order status progression | ??? + ??? | in respective branches |

**Done definition:** A customer can complete a purchase. Vendor can confirm and move the order through all statuses. Customer can view their order history.

---

### Sprint 4 — Suki Features & Messaging
**Goal:** Favorites system and buyer-seller messaging are live.

| Task | Owner | Branch |
|---|---|---|
| Favorites toggle on vendor / product pages | ??? | `feature/favorites` |
| Favorites page (followed vendor storefronts) | ??? | in above branch |
| Messaging inbox (conversation list) | ??? | `feature/messaging` |
| Conversation thread view with wire:poll | ??? | in above branch |
| Unread notification badge in app header | ??? | `feature/notification-badge` |
| Admin user management page | ??? | `feature/admin-users` |
| Admin all-orders oversight page | ??? | `feature/admin-orders` |
| Tests for favorites + messaging | ??? | in respective branches |

**Done definition:** Customer can follow vendors, receive updates, and message sellers directly tied to an order.

---

### Sprint 5 — Analytics, Polish & Final QA
**Goal:** Sales summary for vendors, final UI polish, all tests passing, deployment ready.

| Task | Owner | Branch |
|---|---|---|
| Vendor sales summary (revenue by period) | ??? | `feature/vendor-sales` |
| Storefront pagination edge cases + SEO meta | ??? | `fix/storefront-polish` |
| Empty states, loading states across all pages | all | `style/empty-states` |
| Full test suite audit (all green) | all | fix branches as needed |
| `.env.example` + `README` finalized | ??? | `docs/readme` |
| Final deployment to production host | ??? | N/A |

---

## 7. How to Build Any Feature (Standard Order)

Always follow this exact sequence when adding a new feature:

```
1.  Enum          app/Enums/              (only if a new status/type is needed)
2.  Migration     database/migrations/    (enum column pattern, correct timestamps)
3.  Model         app/Models/             (#[Fillable], casts() method, relationships)
4.  Factory       database/factories/     (state methods, afterCreating side-effects)
5.  Route         routes/web.php          (correct role middleware group)
6.  Controller    app/Http/Controllers/   (only for non-Livewire pages)
7.  Livewire page resources/views/pages/  (⚡ prefix, inline class, Flux components)
8.  Pest test     tests/Feature/          (guests, happy path, edge cases, role gates)
```

**Never skip the test.** A feature with no test is not considered complete.

---

## 8. Code Conventions Cheatsheet

### Models — always use PHP attribute syntax

```php
//  correct
#[Fillable(['name', 'status', 'amount'])]
class MyModel extends Model

//  wrong
protected $fillable = ['name', 'status', 'amount'];
```

### Casts — always a method, never a property

```php
//   correct
protected function casts(): array
{
    return ['status' => MyEnum::class];
}

//   wrong
protected $casts = ['status' => 'string'];
```

### Livewire pages — always inline, always ⚡ prefix

```php
//   correct filename: resources/views/pages/vendor/⚡products.blade.php
new #[Title('My Products')] class extends Component { ... }; ?>

//   wrong — no separate PHP class file for pages
//   wrong — no blade file without ⚡ prefix for Livewire pages
```

### Flux components — always Flux, never raw HTML form elements

```blade
{{--   correct --}}
<flux:input wire:model="name" :label="__('Name')" type="text" required />
<flux:button variant="primary" type="submit">Save</flux:button>

{{--   wrong --}}
<input type="text" wire:model="name" class="...">
<button type="submit" class="...">Save</button>
```

### Role checks — always use User model methods, never Spatie helpers at runtime

```php
//   correct
$user->canAccessMarketplaceRole(UserRole::Vendor)
$user->hasMarketplaceRole(UserRole::Admin)
$user->effectiveMarketplaceRole()

//   wrong
$user->hasRole('vendor')        // Spatie method — not used at runtime
$user->role === 'vendor'        // string comparison — use enum
```

### Scoping vendor data — always scope to the authenticated vendor

```php
//   correct
$products = auth()->user()->vendorProfile->products()->paginate(12);

//   wrong — never trust a route parameter alone
$products = Product::where('vendor_id', $request->vendor_id)->get();
```

---

## 9. Testing Rules

### File location mirrors the feature area

```
tests/Feature/Auth/          ← login, register, 2FA, password reset
tests/Feature/Admin/         ← vendor approval, user management
tests/Feature/Vendor/        ← products, orders, sales
tests/Feature/Customer/      ← cart, checkout, favorites, messaging
tests/Feature/Shop/          ← storefront browsing
tests/Feature/Settings/      ← profile, security, appearance
```

### Every test file must cover

1. **Guest redirect** — unauthenticated request redirected to login
2. **Role gate** — wrong role redirected to own portal
3. **Happy path** — the main success scenario
4. **Edge cases** — empty states, validation failures, boundary conditions

### Running tests

```bash
# Run the full suite
php artisan test

# Run one test file
php artisan test tests/Feature/Vendor/ProductManagementTest.php

# Run tests matching a name
php artisan test --filter="vendor can create a product"

# Run with coverage
php artisan test --coverage
```

### Test database

Tests use `DB_DATABASE=sukimarket_testing` (see `phpunit.xml`).
The `LazilyRefreshDatabase` trait in `Pest.php` handles all migrations automatically.
**Never seed test data manually** — always use factories.

---

## 10. Pull Request Checklist

Before opening a PR, confirm every item below:

```
[ ] Branch is up to date with develop (git pull origin develop, then rebase or merge)
[ ] All new code follows conventions (Fillable attributes, casts() method, Flux components)
[ ] All new routes are in the correct role middleware group
[ ] Images / file uploads use the public disk and storage:link
[ ] Vendor data is scoped to auth()->user()->vendorProfile — no cross-vendor leaks
[ ] No raw SQL or DB::statement() — use Eloquent and Query Builder
[ ] php artisan test passes with no failures
[ ] composer lint:check passes (Pint formatter)
[ ] PR title follows commit format: feat(module): description
[ ] PR description lists what was added, changed, and which tests cover it
[ ] Reviewer assigned (anyone not the author)
```

### Fixing lint errors before pushing

```bash
# Auto-fix formatting
composer lint

# Check only (what CI runs)
composer lint:check
```

---

## 11. Database & Seeding

### Migration rules

- Every new table has its own migration file
- Enum columns always use `array_column(MyEnum::cases(), 'value')` — never hardcoded strings
- Append-only tables (messages, notifications, favorites, carts) use `$table->timestamp('created_at')->useCurrent()` and **no** `updated_at`
- All FK columns are `constrained()` with `cascadeOnDelete()` unless a softer `nullOnDelete()` makes more sense

### Seeder structure

```
DatabaseSeeder
├── RoleSeeder          (admin, vendor, customer roles for Spatie)
├── Admin user          (admin@example.com)
├── Vendor user         (vendor@example.com + approved VendorProfile)
├── Categories          (Vegetables, Fruits, Snacks)
├── Products            (2 active per category)
└── Customer user       (test@example.com)
```

### Common artisan commands

```bash
php artisan migrate                  # run new migrations
php artisan migrate:fresh --seed     # wipe and re-seed (local only!)
php artisan db:seed                  # seed without wiping
php artisan storage:link             # must run once for image uploads to work
```

---

## 12. Deployment Checklist

When the project is ready for final presentation/deployment:

```bash
# 1. Set environment to production
APP_ENV=production
APP_DEBUG=false

# 2. Run migrations on production DB
php artisan migrate --force

# 3. Seed the production database
php artisan db:seed --force

# 4. Create storage symlink
php artisan storage:link

# 5. Cache all config, routes, and views for performance
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 6. Build production assets
npm run build

# 7. Optimize autoloader
composer install --optimize-autoloader --no-dev
```

### Things to double-check before presentation

```
[ ] APP_KEY is set and non-empty
[ ] DB credentials point to the correct production database
[ ] MAIL_* values are set if email verification is being demonstrated
[ ] storage:link has been run and /public/storage exists
[ ] At least one approved vendor with active products is seeded
[ ] Admin, vendor, and customer accounts are seeded and working
[ ] All three portals (admin, vendor, customer) are accessible
[ ] php artisan test passes against the testing database
```

---

*Last updated: April 20 2026 — SukiMarket for IT9a*
