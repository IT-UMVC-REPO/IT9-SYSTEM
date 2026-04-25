# Contributing to SukiMarket

SukiMarket is a Laravel 13 + Livewire 4 academic prototype built for the IT Professional Track 3 (IT9a/L) subject at the University of Mindanao. The project digitizes the Filipino wet market experience through a multi-role web platform serving customers, vendors, and an admin. This document is the team's living development roadmap — use it to track what has shipped, what is a confirmed placeholder, and what still needs to be built.

---

## Current Build Status

The following features are confirmed working in the current codebase:

- **Public landing page** at `/` with a featured vendor showcase and role-aware CTAs
- **Role-aware `/dashboard` redirect** — routes each authenticated user to their correct portal home (`customer.dashboard`, `vendor.dashboard`, or `admin.dashboard`)
- **Full authentication flow** via Laravel Fortify — register, login, logout, email verification, password reset, and two-factor authentication (TOTP + recovery codes)
- **Customer storefront** (`shop.home`) with server-side keyword search, category filter, max price filter, sort options, and paginated results showing only active products from approved vendors
- **Product detail page** (`shop.products.show`) with quantity controls, sold-out state, trust badges (COD / GCash & Maya / Vendor support), and a message vendor CTA stub
- **Seeded demo marketplace dataset** — `php artisan migrate --seed` provisions categories, 15 approved vendors, 95 users, 120 orders, payments, messages, notifications, and cart data
- **Appearance settings** — light / dark / system theme toggle persisted via Flux
- **Account settings** — profile update, password update, account deletion, and 2FA management (enable, confirm, disable, recovery codes)
- **Role-protected portal routing** — `EnsureUserHasRole` middleware enforces correct redirect behavior; pending/rejected vendors are treated as customers
- **Comprehensive Pest test suite** — all tests currently passing, covering authentication, storefront, placeholder pages, marketplace foundation, and settings

---

## Claiming a Task

1. Edit this file — find the task row you are taking on and replace `—` in **Claimed by** with your name.
2. Update **Status** to `In progress`.
3. When done and all tests pass, update **Status** to `Done` and open a pull request against `develop`.
4. Run `composer test` locally before pushing — the CI pipeline runs the same check.

## Team Members

| Name | Currently working on |
|------|----------------------|
| Trisha Mae Llano | — |
| Kate Kryztel Valdez | — |
| Joshua Miguel Moran | — |


---

## Placeholder Pages (Confirmed TBD Shells)

Every route below currently renders a minimal `TBD` placeholder shell (see `resources/views/components/placeholder-page.blade.php`) and has no production business logic.

| Route name | URL | Description of what it needs |
|---|---|---|
| `shop.cart` | `/shop/cart` | Real cart page showing `CartItem` records with quantity controls and single-vendor guardrail |
| `shop.checkout` | `/shop/checkout` | Checkout form with delivery address, order notes, payment method selection, and order creation |
| `shop.orders` | `/shop/orders` | Paginated customer order history with status chips |
| `shop.orders.show` | `/shop/orders/{orderReference}` | Full customer order detail — line items, totals, payment status, status timeline |
| `shop.favorites` | `/shop/favorites` | Followed-vendor list with unfollow actions |
| `shop.vendors` | `/shop/vendors` | Approved-vendor directory with search and category filters |
| `shop.vendors.show` | `/shop/vendors/{vendorProfile}` | Full vendor storefront — store identity hero, active product catalog, follow + message CTAs |
| `vendor.registration` | `/vendor/register` | Customer-to-vendor onboarding form that creates a pending `VendorProfile` |
| `vendor.dashboard` | `/vendor/dashboard` | Vendor portal home with live product counts, order totals, and sales summaries |
| `vendor.products` | `/vendor/products` | Vendor-scoped paginated product table with catalog filters |
| `vendor.products.create` | `/vendor/products/create` | Product creation form with image upload |
| `vendor.products.edit` | `/vendor/products/{productReference}/edit` | Prefilled product edit form |
| `vendor.orders` | `/vendor/orders` | Vendor order queue with status filters and fulfillment actions |
| `vendor.orders.show` | `/vendor/orders/{orderReference}` | Seller order detail page with status progression controls |
| `vendor.sales` | `/vendor/sales` | Vendor sales summary — revenue, order counts, and top products by period |
| `admin.dashboard` | `/admin/dashboard` | Admin portal home with approval queue snapshots and marketplace alerts |
| `admin.vendors` | `/admin/vendors` | Paginated vendor review list grouped by approval status |
| `admin.vendors.show` | `/admin/vendors/{vendorReference}` | Single-vendor application review page with approve/reject actions |
| `admin.users` | `/admin/users` | Searchable, role-filterable user account table |
| `admin.orders` | `/admin/orders` | Read-only platform-wide order table with status and vendor filters |
| `messages.inbox` | `/messages` | Buyer–seller conversation list with unread indicators |
| `messages.conversation` | `/messages/{conversationReference}` | Live message thread with reply composer and linked order context |

---

## Development Roadmap

Each module below describes a self-contained unit of work. Tasks within a module should generally be completed in order. Complexity ratings: **S** = a few hours, **M** = a day or two, **L** = several days, **XL** = a week or more.

---

### Module A — Vendor Registration & Onboarding

Customers who want to sell must be able to submit a vendor application from `vendor.registration`. The `VendorProfile` model and migration already exist; this module wires up the front-end form and submission logic.

| # | Task | Complexity | Depends on | Claimed by | Status |
|---|---|---|---|---|---|
| A1 | Build the vendor registration Livewire page (`vendor.registration`) — collect store name, description, and store image (upload to `public` disk); on submit, create a `VendorProfile` with `Pending` status linked to the authenticated user | M | — | — | Not started |
| A2 | After successful submission, redirect the vendor user to a dedicated "pending approval" message screen instead of the customer dashboard | S | A1 | — | Not started |
| A3 | Pest tests for the registration form — validation rules, successful submission creates correct `VendorProfile` record, duplicate submission is prevented | S | A1 | — | Not started |

---

### Module B — Admin: Vendor Approval Workflow

Admins need to review, approve, and reject pending vendor applications. All relevant models (`VendorProfile`, `Notification`) and their migrations already exist.

| # | Task | Complexity | Depends on | Claimed by | Status |
|---|---|---|---|---|---|
| B1 | Replace `admin.vendors` placeholder with a paginated `VendorProfile` table — tabbed by status (Pending / Approved / Rejected), showing store name, submitted date, and a link to the review page | M | A1 | — | Not started |
| B2 | Replace `admin.vendors.show` placeholder with a full vendor review page — store profile details, owner name, submitted store image, and approve / reject action buttons | M | B1 | — | Not started |
| B3 | Approve action: set `VendorProfile.status` to `Approved`, record `approved_at`, update `user.role` to `vendor`, and create a `Notification` record for the vendor | S | B2 | — | Not started |
| B4 | Reject action: set `VendorProfile.status` to `Rejected`, save the required `rejection_reason`, and create a `Notification` record for the vendor | S | B2 | — | Not started |
| B5 | Pest tests for approval and rejection workflows — status transitions, notification creation, and that approved vendors gain portal access | S | B3, B4 | — | Not started |

---

### Module C — Admin: User Management

Admins need visibility into all marketplace accounts and the ability to activate or deactivate them. The `User` model, `is_active` column, and `EnsureUserHasRole` middleware are all in place.

| # | Task | Complexity | Depends on | Claimed by | Status |
|---|---|---|---|---|---|
| C1 | Replace `admin.users` placeholder with a searchable, role-filterable paginated user table — columns: name, email, role, active status, registered date | M | — | — | Not started |
| C2 | Add an activate / deactivate toggle that flips `User.is_active` — deactivated users cannot log in (already enforced by `FortifyServiceProvider::configureAuthentication`) | S | C1 | — | Not started |
| C3 | Pest tests for user listing, search, role filter, and activation toggle | S | C1, C2 | — | Not started |

---

### Module D — Admin: Order Oversight

Admins need a read-only view of all orders across the entire marketplace. This module depends on real orders being created by Module G.

| # | Task | Complexity | Depends on | Claimed by | Status |
|---|---|---|---|---|---|
| D1 | Replace `admin.orders` placeholder with a read-only paginated order table across all vendors — filterable by order status and vendor, showing customer name, vendor name, total, and payment status | M | Module G | — | Not started |
| D2 | Pest tests for the order overview page — correct data displayed, filters narrow results, vendor-scoped data not exposed in unintended ways | S | D1 | — | Not started |

---

### Module E — Vendor: Product Management (CRUD)

Approved vendors need to manage their catalog. All models (`Product`, `Category`, `VendorProfile`) and migrations exist. The `Product::scopeActive()` and `Product::scopeVisibleToCustomers()` scopes must be preserved.

| # | Task | Complexity | Depends on | Claimed by | Status |
|---|---|---|---|---|---|
| E1 | Replace `vendor.products` placeholder with a vendor-scoped paginated product table — columns: image thumbnail, name, category, price, stock, status — with search and status filter | M | Module A | — | Not started |
| E2 | Replace `vendor.products.create` placeholder with a create form — name, description, category (select from leaf categories only), price, stock quantity, image upload to `public` disk, active/inactive status toggle | M | E1 | — | Not started |
| E3 | Replace `vendor.products.edit` placeholder with a prefilled edit form mirroring the create form, pre-loaded with the existing product data | M | E2 | — | Not started |
| E4 | Scope all product queries strictly to `auth()->user()->vendorProfile` — a vendor must never be able to view or edit another vendor's products | S | E1 | — | Not started |
| E5 | Pest tests: create product, edit product, scope isolation (vendor cannot access another vendor's product), image validation, inactive product not visible on storefront | M | E2, E3, E4 | — | Not started |

---

### Module F — Cart

The cart infrastructure (`Cart`, `CartItem` models and migrations) already exists. The product detail page currently fires a toast stub on "Add to cart" — this module replaces that stub with real persistence.

| # | Task | Complexity | Depends on | Claimed by | Status |
|---|---|---|---|---|---|
| F1 | Replace `shop.cart` placeholder with a real cart page — list `CartItem` records for the authenticated customer's `Cart` with product image, name, vendor, unit price, quantity controls (increment / decrement / remove line), and order subtotal | M | — | — | Not started |
| F2 | Implement add-to-cart — replace the toast stub on `shop.products.show` with a Livewire action that upserts a `CartItem` (creates the customer's `Cart` if it does not exist) | M | — | — | Not started |
| F3 | Enforce single-vendor constraint: if the customer adds a product from a different vendor than the one already in their cart, show a confirmation warning and clear the cart before adding the new item | S | F2 | — | Not started |
| F4 | Display a cart item count badge on the cart icon in the header navbar, reactive to real-time cart state | S | F2 | — | Not started |
| F5 | Pest tests: add to cart creates correct records, quantity increment/decrement, remove item, single-vendor constraint clears and replaces cart | M | F1, F2, F3 | — | Not started |

---

### Module G — Checkout & Order Creation

This is the core transaction module. It depends on Module F for a populated cart and produces the `Order`, `OrderItem`, and `Payment` records that every subsequent module relies on.

| # | Task | Complexity | Depends on | Claimed by | Status |
|---|---|---|---|---|---|
| G1 | Replace `shop.checkout` placeholder with a checkout form — delivery address (pre-filled from `user.address`, editable), order notes (optional), and payment method selection (COD / GCash / Maya) | L | Module F | — | Not started |
| G2 | On checkout submit: validate form, create `Order` + `OrderItem` records from the cart contents, create a matching `Payment` record with `Pending` status, clear the cart, redirect to the new order's detail page | L | G1 | — | Not started |
| G3 | GCash / Maya integration via PayMongo API — create a payment intent on checkout, redirect customer to the PayMongo-hosted payment page, handle the webhook callback to mark `Payment.status = Paid` and `Order.payment_status = Paid` | XL | G2 | — | Not started |
| G4 | Pest tests: checkout form validation, successful order creation with correct `OrderItem` records, cart cleared after checkout, COD order sets correct payment status | M | G1, G2 | — | Not started |

---

### Module H — Customer Order Tracking

Customers need to see their order history and track individual orders. Real orders must exist (Module G).

| # | Task | Complexity | Depends on | Claimed by | Status |
|---|---|---|---|---|---|
| H1 | Replace `shop.orders` placeholder with a paginated customer order history list — show order number, vendor name, total, order status chip, and payment status; newest first | M | Module G | — | Not started |
| H2 | Replace `shop.orders.show` placeholder with a full customer order detail page — line items with unit prices, order total, vendor name, delivery address, payment method, payment status, and a readable status timeline | M | H1 | — | Not started |
| H3 | Pest tests for order list and order detail — correct records shown, customer cannot view another customer's order | S | H1, H2 | — | Not started |

---

### Module I — Vendor: Order Management

Vendors need to accept and fulfill incoming orders. Depends on real orders from Module G.

| # | Task | Complexity | Depends on | Claimed by | Status |
|---|---|---|---|---|---|
| I1 | Replace `vendor.orders` placeholder with a vendor-scoped order queue — tabbed by status (Pending / Confirmed / Preparing / Ready / Delivered / Cancelled), showing customer name, item count, total, and order date | M | Module G | — | Not started |
| I2 | Replace `vendor.orders.show` placeholder with a seller order detail page — line items, customer delivery address, notes, payment info, and action buttons to advance status (`Pending → Confirmed → Preparing → Ready → Delivered`) plus a Cancel action | M | I1 | — | Not started |
| I3 | Each status change must create a `Notification` record for the customer with the new status message | S | I2 | — | Not started |
| I4 | Pest tests: status transitions in correct order, vendor cannot access another vendor's orders, notification created on status change | M | I2, I3 | — | Not started |

---

### Module J — Vendor: Sales Reporting

Vendors need a basic sales overview. Depends on real completed orders (Module G).

| # | Task | Complexity | Depends on | Claimed by | Status |
|---|---|---|---|---|---|
| J1 | Replace `vendor.sales` placeholder with a sales summary page — total revenue, total order count, and top 5 products by revenue for selectable periods: This Week / This Month / All Time | L | Module G | — | Not started |
| J2 | Pest tests for the sales page — correct revenue totals, period filter narrows results, vendor scope isolation | S | J1 | — | Not started |

---

### Module K — Messaging (Buyer ↔ Vendor)

The `Message` model and migration already exist. The product detail page already has a "Message vendor" CTA stub linking to the inbox placeholder.

| # | Task | Complexity | Depends on | Claimed by | Status |
|---|---|---|---|---|---|
| K1 | Replace `messages.inbox` placeholder with a real conversation list page — group `Message` records by unique sender/receiver pairs, show most recent message preview, timestamp, and unread indicator (bold / dot) | L | — | — | Not started |
| K2 | Replace `messages.conversation` placeholder with a live thread page — chronological message timeline, reply composer (textarea + send button), and a linked order context panel showing order number and status if `order_id` is present | L | K1 | — | Not started |
| K3 | Poll for new messages every 5 seconds using `wire:poll` on the conversation page so replies appear without a manual refresh | S | K2 | — | Not started |
| K4 | Mark all messages in a thread as `is_read = true` when the conversation page is opened | S | K2 | — | Not started |
| K5 | Connect the "Message vendor" CTA on `shop.products.show` — clicking it should create or open the correct vendor thread (using the vendor's `user_id` as `receiver_id`) | S | K2 | — | Not started |
| K6 | Pest tests: new message creates correct record, reply is scoped to the correct thread, messages marked read on open, customer and vendor cannot read each other's unrelated threads | M | K1, K2, K4 | — | Not started |

---

### Module L — Favorites (Suki System)

The `Favorite` model and migration already exist. The storefront vendor cards already have a follow button stub (toggle via `onclick`).

| # | Task | Complexity | Depends on | Claimed by | Status |
|---|---|---|---|---|---|
| L1 | Replace `shop.favorites` placeholder with a real followed-vendor list — vendor card with store name, active listing count, and an unfollow button | M | — | — | Not started |
| L2 | Connect the follow / unfollow toggle on storefront vendor cards (currently a local Alpine stub) to a Livewire action that upserts or deletes the `Favorite` record for the authenticated customer | S | L1 | — | Not started |
| L3 | Pest tests: follow creates record, unfollow deletes record, favorites list shows only the authenticated customer's followed vendors | S | L1, L2 | — | Not started |

---

### Module M — Vendor & Product Discovery Pages

These pages let customers browse and follow vendors directly, separate from the main product storefront.

| # | Task | Complexity | Depends on | Claimed by | Status |
|---|---|---|---|---|---|
| M1 | Replace `shop.vendors` placeholder with a real approved-vendor directory — vendor cards showing store name, store image initials, active listing count, and a follow button | M | Module L | — | Not started |
| M2 | Replace `shop.vendors.show` placeholder with a full vendor storefront page — store identity hero (name, description, active listing count), active product catalog grid, follow button, and message vendor CTA | L | Module L, Module K | — | Not started |
| M3 | Pest tests for vendor directory and storefront — only approved vendors appear, pending/rejected return 404 on `show`, follow button reflects correct state | S | M1, M2 | — | Not started |

---

### Module N — Notifications

The `Notification` model and migration exist and the seeder already creates notification records. This module surfaces them in the UI.

| # | Task | Complexity | Depends on | Claimed by | Status |
|---|---|---|---|---|---|
| N1 | Build a notification dropdown/panel in the header navbar — list unread `Notification` records for the authenticated user (title, message, type icon, timestamp), with a "Mark all as read" action that sets `is_read = true` on all | M | — | — | Not started |
| N2 | Confirm that all real-flow notification-creation points fire correctly (vendor approval from Module B, order status changes from Module I, new messages from Module K) — add any missing `Notification::create()` calls | S | Modules B, I, K | — | Not started |
| N3 | Pest tests — unread count is correct, mark-all-read clears the badge, notifications are user-scoped | S | N1, N2 | — | Not started |

---

### Module O — Live Search

The current storefront search is a full-page GET form reload. This module converts it to an instantaneous, no-redirect Livewire experience with debounced keyword input and reactive filter dropdowns. All existing `Product` scopes (`scopeSearch`, `scopeVisibleToCustomers`, `scopeActive`) and `Category` queries must be preserved exactly — only the delivery mechanism changes.

| # | Task | Complexity | Depends on | Claimed by | Status |
|---|---|---|---|---|---|
| O1 | Convert `shop.home` from a plain controller action to a full Livewire page (`⚡home.blade.php`) with public properties `$search`, `$categoryId`, `$maxPrice`, and `$sort`, each decorated with `#[Url]` so the browser URL stays in sync. Move all query logic from `ShopController::index()` into a `#[Computed]` property `products()` | L | — | — | Not started |
| O2 | Apply `wire:model.live.debounce.400ms="search"` to the keyword input; replace the GET form submit button with a Livewire `resetFilters()` action that sets all four properties back to their defaults | M | O1 | — | Not started |
| O3 | Apply `wire:model.live` to the category, max price, and sort dropdowns so any change immediately re-runs the computed query | M | O1 | — | Not started |
| O4 | Wrap the product grid and result count paragraph in `wire:loading.class="opacity-50 pointer-events-none"` to indicate loading during network round-trips | S | O1 | — | Not started |
| O5 | Add a `#[Computed]` property `categories()` that replicates the existing parent/child visible-category query from `ShopController`. Do not inline or duplicate any scope logic inside the component | S | O1 | — | Not started |
| O6 | Remove `ShopController::index()` and its `Route::get` binding once the Livewire page is confirmed working; replace with `Route::livewire('/', 'pages::shop.home')->name('shop.home')` in `routes/web.php` | S | O1, O2, O3 | — | Not started |
| O7 | Pest tests: keyword search updates results without a redirect, category filter narrows listing, max price filter excludes products above threshold, clearing filters restores full visible listing, `#[Url]` properties hydrate correctly on direct URL navigation | M | O1, O2, O3 | — | Not started |


---

## Local Development Reference

### Setup

1. Install PHP and Node.js dependencies:

   ```bash
   git clone [REPO GIT LINK] sukimarket
   cd sukimarket
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

### Demo Accounts

After seeding, you can sign in with these stable accounts:

| Role | Email | Password | Landing Route |
|------|-------|----------|---------------|
| Customer | `test@example.com` | `password` | `shop.home` |
| Approved vendor | `vendor@example.com` | `password` | `vendor.dashboard` |
| Admin | `admin@example.com` | `password` | `admin.dashboard` |

Pending or rejected vendors do not get the vendor dashboard until they are approved.

### Quality Checks

- Format PHP changes: `vendor/bin/pint --dirty --format agent`
- Run the project's standard lint command: `composer lint`
- Run the test suite: `php artisan test --compact`
- Run the full CI-style local check: `composer test`

### Branch Naming Convention

```
feature/<module-letter>-<short-description>   e.g. feature/E-product-crud
fix/<short-description>                        e.g. fix/cart-vendor-constraint
```

---

## Git Guide for the Team

This section is for teammates who are new to Git or just need a quick reference. Read through it once — it covers everything you will need for day-to-day work on this project.

---

### Core concepts (read this first)

- **Repository (repo)** — the project folder that Git is tracking, including all its history.
- **Branch** — a separate copy of the code where you can work without affecting everyone else. Think of it as your personal draft.
- **Commit** — a saved snapshot of your changes, like a checkpoint in a game.
- **Push** — uploading your commits from your computer to GitHub.
- **Pull** — downloading the latest changes from GitHub to your computer.
- **Merge / Pull Request (PR)** — the process of combining your branch back into the shared codebase after review.

The golden rule: **never work directly on `main` or `develop`.** Always create your own branch first.

---

### Initial setup (do this once)

After cloning the repository, tell Git who you are:

```bash
git config --global user.name "Your Name"
git config --global user.email "your@email.com"
```

---

### Starting a new task

**Step 1 — Make sure you are on `develop` and it is up to date.**

```bash
git checkout develop
git pull origin develop
```

**Step 2 — Create your branch using the naming convention.**

```bash
git checkout -b feature/E-product-crud
```

Replace `E-product-crud` with something that matches your actual task (see Branch Naming Convention above). You are now on your own branch and safe to work.

---

### Saving your work (committing)

After making changes to files, save them as a commit:

```bash
git add .
git commit -m "Add product listing table for vendor dashboard"
```

Write commit messages in plain English describing *what* you did. Commit often — small commits are easier to review and easier to undo if something goes wrong.

---

### Uploading your work to GitHub (pushing)

The first time you push a new branch:

```bash
git push -u origin feature/E-product-crud
```

After that first push, you can just run:

```bash
git push
```

---

### Keeping your branch up to date

While you are working, other teammates may merge their changes into `develop`. Pull those updates into your branch regularly so you don't fall too far behind:

```bash
git checkout develop
git pull origin develop
git checkout feature/E-product-crud
git merge develop
```

If Git shows a **merge conflict** (it will say `CONFLICT` in the output), see the Conflicts section below.

---

### Opening a Pull Request (PR)

When your task is done and tests pass (`composer test`):

1. Push your branch to GitHub one final time: `git push`
2. Go to the GitHub repository in your browser.
3. GitHub will show a yellow banner saying your branch was recently pushed — click **"Compare & pull request"**.
4. Set the **base branch** to `develop` (not `main`).
5. Write a short description of what you built or fixed.
6. Submit the PR and let a teammate review it before it gets merged.

---

### Switching between branches

If you need to jump to a different branch temporarily:

```bash
# Save any uncommitted work first
git stash

# Switch to the other branch
git checkout develop

# When you come back, restore your saved work
git checkout feature/E-product-crud
git stash pop
```

---

### Handling merge conflicts

A conflict happens when two people edited the same part of the same file. Git cannot decide which version to keep, so it asks you to choose.

Conflicted files will contain markers like this:

```
<<<<<<< HEAD
Your version of the line
=======
Their version of the line
>>>>>>> develop
```

To fix it:

1. Open the file in your editor.
2. Delete the conflict markers (`<<<<<<<`, `=======`, `>>>>>>>`).
3. Keep whichever version is correct (or combine both if needed).
4. Save the file, then run:

```bash
git add .
git commit -m "Resolve merge conflict in product blade"
```

If you are unsure which version to keep, ask a teammate before committing.

---

### Undoing mistakes

| Situation | Command |
|---|---|
| Undo unsaved changes in one file | `git checkout -- filename.php` |
| Undo all unsaved changes everywhere | `git checkout -- .` |
| Undo the last commit but keep the changes | `git reset --soft HEAD~1` |
| See what changed before committing | `git diff` |
| See the list of your commits | `git log --oneline` |

**Never use `git reset --hard` unless you are absolutely sure** — it permanently deletes uncommitted changes with no undo.

---

### Everyday command cheatsheet

```bash
# See the current state of your files
git status

# See what branch you are on and list all local branches
git branch

# Download the latest changes without switching branches
git fetch origin

# See a short log of recent commits
git log --oneline -10
```

---

### Common mistake quick-fixes

**"I committed to `develop` by accident instead of my branch"**

```bash
# Copy the commit to a new branch first
git checkout -b feature/my-actual-branch
git push -u origin feature/my-actual-branch

# Then undo the accidental commit on develop (your changes are NOT deleted)
git checkout develop
git reset --soft HEAD~1
```

**"I want to rename my branch"**

```bash
git branch -m old-branch-name new-branch-name
git push origin -u new-branch-name
git push origin --delete old-branch-name
```

**"I accidentally deleted a file"**

```bash
git checkout -- path/to/deleted-file.php
```

**"I pushed something and need to take it back"** — talk to a teammate first. Rewriting shared history can cause problems for everyone else on the project.

---

### When in doubt

- Run `git status` — it almost always tells you exactly what to do next.
- Ask a teammate. It is always safer to ask than to guess.
- Do not force-push (`git push --force`) to any branch that others might be using.