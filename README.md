# LocalPalengke

LocalPalengke is a Laravel + Livewire marketplace prototype for Filipino wet-market shopping. The current build includes a public landing page, role-aware portal routing, a seeded customer storefront, and customer, vendor, rider, and admin experiences.

## Current Product Surface

- Public landing page at `/`
- Shared `/dashboard` entry point that redirects users to their role-specific home route
- Customer storefront with search, category filtering, visible-product rules, and seeded vendor/product data
- Featured vendor links on the storefront that now open the placeholder vendor directory and vendor detail pages
- Product detail page with a dedicated purchase block, vendor messaging call-to-action, and trust badges
- Rider registration, admin approval, delivery claiming, rider location updates, and order tracking surfaces
- Messaging, maps, order tracking, and marketplace management routes wired into the shared role-aware shell

## Stack

- PHP 8.5
- Laravel 13
- Laravel Fortify
- Livewire 4
- Flux UI 2
- Tailwind CSS 4
- Pest 4
- PHPUnit 12
- MySQL

## Local Setup

1. Install PHP and Node.js dependencies:

   ```bash
   git clone [REPO GIT LINK] localpalengke
   cd localpalengke
   git checkout devtest
   composer install
   npm install
   ```

2. Create your environment file and configure MySQL:

   ```bash
   copy .env.example .env
   php artisan key:generate
   ```

3. Update `.env` with your local MySQL database credentials.

4. Run migrations and seed the demo marketplace data:

   ```bash
   php artisan migrate --seed
   ```

5. Start the local app, queue listener, and Vite dev server together:

   ```bash
   composer dev
   ```

The app is served locally through Laravel's default dev server, typically at `http://127.0.0.1:8000`.

## Demo Accounts

After seeding, you can sign in with these stable accounts:

| Role | Email | Password | Landing Route |
| --- | --- | --- | --- |
| Customer | `test@example.com` | `password` | `shop.home` |
| Approved vendor | `vendor@example.com` | `password` | `vendor.dashboard` |
| Approved rider | `rider@example.com` | `password` | `rider.dashboard` |
| Admin | `admin@example.com` | `password` | `admin.dashboard` |

Pending or inactive vendors and riders do not get their role dashboards until they are approved.

## Route Overview

- `home`: public landing page
- `dashboard`: authenticated redirect to the user's role-specific home route
- `customer.dashboard`: customer portal entry
- `shop.home`: storefront
- `shop.products.show`: product detail page
- `shop.vendors` and `shop.vendors.show`: placeholder vendor browse/detail pages linked from the storefront
- `messages.inbox` and `messages.conversation`: placeholder messaging pages for customers and vendors
- `vendor.dashboard`: vendor portal entry
- `rider.registration`, `rider.dashboard`, `rider.deliveries`, and `rider.history`: rider onboarding and delivery workspace
- `admin.dashboard`: admin portal entry

## Placeholder Pages

The following routes are intentionally wired to minimal `TBD` placeholder screens while the production flows are still being built:

- Customer: `shop.vendors`, `shop.vendors.show`, `shop.cart`, `shop.checkout`, `shop.orders`, `shop.orders.show`, `shop.favorites`
- Messaging: `messages.inbox`, `messages.conversation`
- Vendor: `vendor.registration`, `vendor.products`, `vendor.products.create`, `vendor.products.edit`, `vendor.orders`, `vendor.orders.show`, `vendor.sales`
- Admin: `admin.vendors`, `admin.vendors.show`, `admin.users`, `admin.orders`

## Quality Checks

- Format PHP changes: `vendor/bin/pint --dirty --format agent`
- Run the project's standard lint command: `composer lint`
- Run the test suite: `php artisan test --compact`
- Run the full CI-style local check: `composer test`

## Notes

- Storefront and product-detail behavior is covered in `tests/Feature/Shop/StorefrontTest.php`.
- If frontend changes do not appear locally, make sure `composer dev` and `npm run dev` is running.
