# Contributing to LocalPalengke

LocalPalengke is a Laravel 13 + Livewire 4 marketplace for the Filipino wet market experience. This document reflects the current implementation state in the repository so contributors can verify what is already shipped before planning new work.

---

## Current Build Status

The current codebase includes the following implemented features:

- Public landing page at `/` with branded marketing content and role-aware CTAs
- Role-aware `/dashboard` redirect to `customer.dashboard`, `vendor.dashboard`, `rider.dashboard`, or `admin.dashboard`
- Full Laravel Fortify authentication flow with email verification, password reset, and two-factor authentication
- Customer storefront with live search, category filtering, price filtering, sorting, and paginated product browsing
- Customer dashboard with recent orders, cart summary, followed stalls, and unread notification count
- Product detail pages with cart actions, vendor messaging CTA, and storefront trust badges
- Vendor directory, vendor storefront pages, and persisted favorites follow/unfollow flows
- Messaging inbox and live conversation threads between marketplace users
- Header notification bell with unread counts and mark-all-read support
- Cart, checkout, customer order history, and customer order detail tracking
- Vendor registration and approval workflow, including admin review and notifications
- Vendor dashboard, product management, order management, and sales reporting
- Rider registration, admin approval, delivery claiming, live location updates, and delivery history
- Admin dashboard, vendor approvals, user management, and marketplace-wide order oversight
- Seeded demo dataset with customers, vendors, products, orders, payments, messages, notifications, and cart data
- Pest feature coverage across authentication, storefront, dashboards, messaging, notifications, vendor tools, admin tools, and settings

---

## Placeholder Status

There are currently no routed marketplace pages using the shared `placeholder-page` shell. The old placeholder-only portal pages have been replaced by real Livewire or controller-backed implementations.

---

## Module Status

| Module | Area | Status |
|---|---|---|
| A | Vendor Registration & Onboarding | Done |
| B | Admin: Vendor Approval Workflow | Done |
| C | Admin: User Management | Done |
| D | Admin: Order Oversight | Done |
| E | Vendor: Product Management (CRUD) | Done |
| F | Cart | Done |
| G | Checkout & Order Creation | Done |
| H | Customer Order Tracking | Done |
| I | Vendor: Order Management | Done |
| J | Vendor: Sales Reporting | Done |
| K | Messaging (Buyer and Vendor) | Done |
| L | Favorites (Your Local Stall) | Done |
| M | Vendor and Product Discovery Pages | Done |
| N | Notifications | Done |
| O | Rider Delivery Workflow | Done |

---

## Local Reference

### Setup

1. Clone the repository and install dependencies:

   ```bash
   git clone [REPO GIT LINK] localpalengke
   cd localpalengke
   composer install
   npm install
   ```

2. Create your environment file and application key:

   ```bash
   copy .env.example .env
   php artisan key:generate
   ```

3. Configure your local MySQL credentials in `.env`.

4. Run migrations and seed the demo dataset:

   ```bash
   php artisan migrate --seed
   ```

5. Start the app, queue worker, and Vite dev server:

   ```bash
   composer dev
   ```

### Demo Accounts

| Role | Email | Password | Landing Route |
|---|---|---|---|
| Customer | `test@example.com` | `password` | `shop.home` |
| Approved vendor | `vendor@example.com` | `password` | `vendor.dashboard` |
| Approved rider | `rider@example.com` | `password` | `rider.dashboard` |
| Admin | `admin@example.com` | `password` | `admin.dashboard` |

Pending or inactive vendors and riders remain customer-facing until approval.

### Quality Checks

- Format PHP changes: `vendor/bin/pint --dirty --format agent`
- Run the compact test suite: `php artisan test --compact`
- Run the full suite: `php artisan test`

### Branch Naming Convention

```text
feature/<module-letter>-<short-description>
fix/<short-description>
```
