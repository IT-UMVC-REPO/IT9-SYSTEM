# SukiMarket - Project Dev Workflow

> Stack: PHP 8.5, Laravel 13, Livewire 4, Flux UI 2, Fortify, Pest 4, MySQL, Tailwind CSS v4

---

## Table of Contents

1. Team Roles
2. Branch & Git Strategy
3. Local Environment Setup
4. Project Status
5. Module Assignments
6. Sprint Plan
7. Build Order
8. Code Conventions Cheatsheet
9. Testing Rules
10. Pull Request Checklist
11. Database & Seeding
12. Deployment Checklist

---

## 1. Team Roles

| Person | Responsibility |
|---|---|
| **Trisha Mae Llano** | ??? |
| **Kryztel Kate Valdez** | ??? |
| **Joshua Miguel Moran** | ??? |

Lead rule: `??` reviews and merges all PRs into `develop`. No one merges their own PR.

---

## 2. Branch & Git Strategy

### Branch naming

```bash
main          # production-ready only (never push directly)
develop       # integration branch (all PRs target here)
feature/      # new features
fix/          # bug fixes
test/         # test-only changes
```

### Example branches

```bash
feature/vendor-product-listings
feature/customer-cart
feature/admin-vendor-approval
fix/order-status-redirect
test/messaging-feature
```

### Day-to-day workflow

```bash
git checkout develop
git pull origin develop

git checkout -b feature/my-feature-name

git add .
git commit -m "feat(vendor): add product listing CRUD with image upload"

git push origin feature/my-feature-name
```

### Commit message format

```text
feat(module): short description
fix(module): short description
test(module): short description
refactor(module): short description
style(module): short description
```

Suggested modules: `auth`, `admin`, `vendor`, `customer`, `cart`, `orders`, `messaging`, `favorites`, `notifications`

### Merge rules

- Every PR must have at least 1 passing CI run before merge
- Every PR must be reviewed by one other team member
- Squash merge into `develop`
- Delete the feature branch after merging

---

## 3. Local Environment Setup

### First-time setup

```bash
git clone <repo-url> sukimarket
cd sukimarket

composer config http-basic.composer.fluxui.dev YOUR_FLUX_USERNAME YOUR_FLUX_LICENSE_KEY
composer install

npm install

cp .env.example .env
php artisan key:generate

php artisan migrate
php artisan db:seed

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

FILESYSTEM_DISK=public
```

### Running the dev server

```bash
composer dev
```

This runs `php artisan serve`, `queue:listen`, and `npm run dev` together.

### Seeded accounts

| Name | Email | Password | Role |
|---|---|---|---|
| Marketplace Admin | admin@example.com | password | Admin |
| Fresh Vendor | vendor@example.com | password | Approved Vendor |
| Test User | test@example.com | password | Customer |

---

## 4. Project Status

### Already built

| Area | What's complete |
|---|---|
| **Auth** | Registration, login, logout, password reset, email verification, 2FA, role-based redirects |
| **Middleware** | `EnsureUserHasRole` with effective role logic (pending/rejected vendor becomes customer access) |
| **Models** | All 12 marketplace models are present |
| **Migrations** | All tables are migrated with enum columns and foreign keys |
| **Factories** | All models have factories with useful states |
| **Customer Storefront** | Browse products, category filter, keyword search, product detail page |
| **Landing Page** | Marketing landing page with live vendor showcase |
| **Settings** | Profile update, password update, 2FA setup, account deletion |
| **Portals** | Separate customer, vendor, and admin dashboards with role-based redirects |
| **Placeholder Pages** | Navbar dummy links now point to real placeholder pages |
| **Layouts** | Role-aware app header, auth layouts, shared TBD placeholder shell |
| **Tests** | Auth suite, settings suite, storefront suite, marketplace foundation suite, placeholder route coverage |

### Placeholder pages now exist

- `customer.dashboard`
- `vendor.dashboard`
- `admin.dashboard`
- `vendor.registration`
- `vendor.products`
- `vendor.products.create`
- `vendor.products.edit`
- `vendor.orders`
- `vendor.orders.show`
- `vendor.sales`
- `admin.vendors`
- `admin.vendors.show`
- `admin.users`
- `admin.orders`
- `shop.cart`
- `shop.checkout`
- `shop.orders`
- `shop.orders.show`
- `shop.favorites`
- `messages.inbox`
- `messages.conversation`

### Still needs real functionality

| Module | Owner | Status |
|---|---|---|
| **Live Search** | ??? | Not started |
| **Vendor Registration Flow** | ??? | Placeholder page ready |
| **Vendor Product CRUD** | ??? | Placeholder pages ready |
| **Vendor Order Management** | ??? | Placeholder pages ready |
| **Vendor Sales Summary** | ??? | Placeholder page ready |
| **Admin Vendor Approval** | ??? | Placeholder pages ready |
| **Admin User Management** | ??? | Placeholder page ready |
| **Admin Order Oversight** | ??? | Placeholder page ready |
| **Customer Cart & Checkout** | ??? | Placeholder pages ready |
| **Customer Order Tracking** | ??? | Placeholder pages ready |
| **Favorites (Suki System)** | ??? | Placeholder page ready |
| **In-App Messaging** | ??? | Placeholder pages ready |
| **Notifications** | ??? / ??? | Not started |
| **Image Upload (Storage)** | ??? (leads) | Not started |

---

## 5. Module Assignments

### ??? - Admin Panel

**Placeholder pages now available:**

```text
resources/views/pages/admin/
  dashboard.blade.php
  vendors.blade.php
  vendor-detail.blade.php
  users.blade.php
  orders.blade.php
```

Current state: all admin routes and pages exist as dummy shells. Navigation is in place, but approval logic, filters, tables, and actions are still TBD.

**Key logic to implement:**

- List all `VendorProfile` records grouped by status
- Approve vendor applications
- Reject vendor applications with required rejection reason
- Notify vendors on approval or rejection
- Add user management search, filters, and account status toggles
- Add read-only admin order oversight

### ??? - Vendor Dashboard

**Placeholder pages now available:**

```text
resources/views/pages/vendor/
  dashboard.blade.php
  registration.blade.php
  products.blade.php
  product-create.blade.php
  product-edit.blade.php
  orders.blade.php
  order-detail.blade.php
  sales.blade.php
```

Current state: vendors have a dedicated portal and all planned seller pages exist as dummy routes/views. CRUD, uploads, order workflows, and reporting are still TBD.

**Key logic to implement:**

- Vendor registration flow from customer to pending vendor
- Vendor-scoped product CRUD
- Product image upload on `public` disk
- Vendor-scoped order list and order detail
- Order status progression: `pending -> confirmed -> preparing -> ready -> delivered`
- Weekly/monthly sales summaries

### ??? - Customer Features

**Placeholder pages now available:**

```text
resources/views/pages/customer/
  dashboard.blade.php

resources/views/pages/shop/
  cart.blade.php
  checkout.blade.php
  orders.blade.php
  order-detail.blade.php
  favorites.blade.php

resources/views/pages/messages/
  inbox.blade.php
  conversation.blade.php
```

Current state: customers now land on their own dashboard instead of the storefront. Cart, checkout, order tracking, favorites, vendor registration, and messaging pages all exist as dummy shells only.

**Key logic to implement:**

- Cart creation and quantity management
- Single-vendor-per-cart enforcement
- Checkout with address, payment method, and notes
- Order creation with `Order`, `OrderItem`, and `Payment`
- Favorites / suki follow system
- Buyer-seller messaging
- Notifications and unread badges

---

## 6. Sprint Plan

### Sprint 1 - Vendor Foundation

Goal: vendor can register, submit a profile, and be reviewed by admin.

### Sprint 2 - Vendor Products & Customer Cart

Goal: vendor can list products, customer can build a cart.

### Sprint 3 - Checkout & Order Lifecycle

Goal: customer can place orders, vendor can process them.

### Sprint 4 - Suki Features & Messaging

Goal: favorites system and buyer-seller messaging are live.

### Sprint 5 - Analytics, Polish & Final QA

Goal: vendor sales summary, UI polish, passing tests, deployment ready.

---

## 7. Build Order

Always build features in this order:

```text
1. Enum
2. Migration
3. Model
4. Factory
5. Route
6. Controller
7. Livewire page / Blade page
8. Pest test
```

Never skip the test.

---

## 8. Code Conventions Cheatsheet

### Models

- Use PHP attribute syntax for fillable
- Use `casts(): array`, not `$casts`
- Use explicit relationships and type hints

### Livewire pages

- Follow current project conventions first
- Use the established page structure already present in `resources/views/pages`

### Role checks

Use:

```php
$user->canAccessMarketplaceRole(UserRole::Vendor);
$user->hasMarketplaceRole(UserRole::Admin);
$user->effectiveMarketplaceRole();
```

Avoid:

```php
$user->hasRole('vendor');
$user->role === 'vendor';
```

### Vendor scoping

Always scope vendor data to the authenticated vendor profile.

---

## 9. Testing Rules

### Test folders

```text
tests/Feature/Auth
tests/Feature/Admin
tests/Feature/Vendor
tests/Feature/Customer
tests/Feature/Shop
tests/Feature/Settings
```

### Every feature test should cover

1. Guest redirect
2. Role gate
3. Happy path
4. Edge cases

### Running tests

```bash
php artisan test
php artisan test tests/Feature/Vendor/ProductManagementTest.php
php artisan test --filter="vendor can create a product"
php artisan test --coverage
```

---

## 10. Pull Request Checklist

- Branch is up to date with `develop`
- Code follows project conventions
- Routes are inside the correct role middleware group
- Uploads use `public` disk and `storage:link`
- Vendor data is properly scoped
- No raw SQL with user input
- `php artisan test` passes
- Pint formatting passes
- PR title follows commit format
- Reviewer assigned

---

## 11. Database & Seeding

### Migration rules

- One table per migration
- Enum columns should use enum cases, not hardcoded strings
- Append-only tables should use `created_at` only when appropriate
- Use proper foreign keys and delete behavior

### Seeder structure

```text
DatabaseSeeder
├── RoleSeeder
├── Admin user
├── Vendor user + approved VendorProfile
├── Categories
├── Products
└── Customer user
```

### Common commands

```bash
php artisan migrate
php artisan migrate:fresh --seed
php artisan db:seed
php artisan storage:link
```

---

## 12. Deployment Checklist

```bash
APP_ENV=production
APP_DEBUG=false

php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
npm run build
composer install --optimize-autoloader --no-dev
```

### Before presentation

- `APP_KEY` is set
- Production DB credentials are correct
- `MAIL_*` is configured if email verification is being demonstrated
- `storage:link` has been run
- At least one approved vendor with active products is seeded
- Admin, vendor, and customer accounts are working
- All three portals are accessible
- Tests pass against the testing database

---

Last updated: April 23, 2026 - SukiMarket for IT9a
