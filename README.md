# Multi-Store E-commerce Platform

A production-grade, multi-tenant e-commerce platform built with **Laravel 12**, **Inertia.js v2**, and **React 19** — purpose-built for Bangladesh and Saudi Arabia markets, with online payments, courier integrations, ZATCA e-invoicing, and a full merchant admin panel.

![Laravel 12](https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel)
![PHP 8.2](https://img.shields.io/badge/PHP-8.2-777BB4?logo=php)
![React 19](https://img.shields.io/badge/React-19-61DAFB?logo=react)
![Inertia v2](https://img.shields.io/badge/Inertia-v2-9553E9)
![Tailwind v4](https://img.shields.io/badge/Tailwind-v4-06B6D4?logo=tailwindcss)
![Pest 3](https://img.shields.io/badge/Tests-820%2B%20passing-brightgreen)

## Highlights

- **Multi-store architecture** — one install serves many stores, each with its own catalog, settings, currency, theme, staff, and domain. Store resolution works via header, query, subdomain, or domain.
- **Payments that survive failure** — SSLCommerz, bKash, Moyasar, Tabby, and Stripe behind a common gateway interface, with expiring order reservations and **idempotent payment retries** on the same pending order (same key replays, in-flight attempts return 409, stale attempts re-execute). No lost carts, no duplicate orders.
- **Code-driven couriers** — Pathao, RedX, Steadfast, Paperfly, eCourier, Sundarban, SA Paribahan, SMSA, and Aramex managed in `config/couriers.php` (no courier rows in the DB). One-click API consignment with auto-filled tracking, plus manual tracking entry, live tracking pulls, and webhook status sync that rolls up to order state.
- **Inventory without oversell** — reservation-based stock (reserve on order, deduct on payment, release on cancel/expire) under row locks, with low-stock and back-in-stock wishlist notifications.
- **Promotions engine** — combinable discount modes (single-winner, waterfall, best-per-line), coupons with usage limits, automatic discounts, and free-shipping thresholds.
- **Verified-purchase reviews** — only approved, verified buyers (or order-verified guests) count toward ratings, with a fully moderated admin queue.
- **ZATCA e-invoicing** — Saudi Phase-1 QR payloads, compliance/production CSID onboarding, and clearance/reporting flows.
- **Merchant admin** — command-center dashboard, RBAC staff roles with per-route permissions, audit logs, themeable UI (light/dark/brand/ocean/beauty), barcode label printing, CMS pages, menus, banners, and homepage sections.
- **Storefront** — server-rendered Inertia SPA with instant search, faceted catalog, flash sales, quick view, guest checkout, and order tracking by email/phone.

## Tech stack

| Layer | Choice |
|---|---|
| Backend | Laravel 12 (PHP 8.2), Eloquent, queued jobs |
| Frontend | React 19 + TypeScript, Inertia.js v2, Tailwind CSS v4, Vite 6 |
| Database | MySQL (dev/prod), SQLite in-memory for tests |
| Testing | Pest 3 — 820+ feature tests, zero skipped |
| Auth | Sanctum tokens (storefront/API), session roles + Spatie permissions (admin) |
| Tooling | Laravel Pint, `tsc`, ESLint, fully documented `.env.example` (180+ keys) |

## Architecture notes

- **Thin controllers, rich services** — `OrderService`, `PaymentService`, `InventoryService`, `CatalogService`, and friends own the business rules; controllers only validate and respond.
- **State machines everywhere** — orders (`pending → confirmed → processing → shipped → delivered`), payments, and shipments move through guarded transitions, so webhooks, retries, and admin actions can never resurrect a cancelled order or double-confirm a paid one.
- **Webhooks fail closed** — every inbound status push is verified server-side before it touches an order; forged payloads are logged, never applied.
- **Cache discipline** — featured/category/brand payloads are cached per store and locale, invalidated by model observers (including stock-flip-aware inventory invalidation); paginated listings are never cached.
- **Migrations are round-trip safe** — the full 88-migration history migrates, rolls back, and re-migrates cleanly on both MySQL and SQLite (verified in CI-style scratch cycles, not just `migrate:fresh`).

## Getting started

Requirements: PHP 8.2+, Composer, Node 20+, MySQL 8 (or MariaDB).

```bash
# 1. Install dependencies
composer install
npm install

# 2. Configure (every key is documented inline)
cp .env.example .env
php artisan key:generate

# 3. Migrate and seed demo data
php artisan migrate --seed

# 4. Build frontend assets
npm run build

# 5. Run everything (server + queue + logs + Vite)
composer run dev
```

Open `http://localhost:8000` for the storefront and `/admin` for the merchant panel.

### Try a test payment

`SSLCOMMERZ_SANDBOX=true` is the default, using SSLCommerz's public test credentials — no real money moves. At checkout pick **Card / Mobile Banking**, then pay with the official sandbox card `4111111111111111` (exp `12/26`, CVV `111`) and OTP `123456`. The order flips to `confirmed` via webhook.

### Try one-click shipping

Add your courier's API key + secret to `.env` (see the `Couriers` section — every key is commented), then open a confirmed order in admin and hit **Send to Courier**. Without keys, use **Add tracking** and paste the consignment number from the courier's own app.

## Testing

```bash
# Full suite (SQLite in-memory, ~1 min)
php artisan test --compact

# One file
php artisan test --compact tests/Feature/CheckoutTest.php

# Style + types
vendor/bin/pint --dirty --format agent
npx tsc --noEmit && npm run lint
```

## Project layout

```
app/
  Http/Controllers/Api/      Customer + webhook endpoints
  Http/Controllers/Admin/    Merchant panel (RBAC per route)
  Models/                    Eloquent models + store scoping
  Observers/                 Cache invalidation on writes
  Services/                  Orders, payments, inventory, catalog…
  Services/Couriers/        Code-driven courier gateways
  Services/PaymentGateways/  SSLCommerz, bKash, Moyasar, Tabby, Stripe
resources/js/
  pages/store/               Storefront (Inertia + React)
  pages/admin/               Merchant admin
  pages/account/             Checkout, orders, order tracking
  components/store/          Shared storefront UI (cards, badges, quick view)
config/couriers.php          Courier catalog, keys, sandbox flags
database/migrations/         88 migrations, fully rollback-safe
tests/Feature/               820+ Pest tests
```

## Environment in 60 seconds

Everything configurable lives in `.env` and is explained line-by-line in `.env.example`. The big levers:

- **Payments** — `PAYMENT_*_ENABLED` toggles which buttons appear at checkout (admin Settings can override per store); `*_SANDBOX=true` keeps gateways in test mode; `VERIFY_PAYMENT_WEBHOOKS` must stay `true` in production.
- **Couriers** — `*_ENABLED` shows a courier in admin; filling its `*_API_KEY`/`*_SECRET_KEY` makes one-click sending work.
- **Store defaults** — `STORE_COUNTRY/CURRENCY/LOCALE/TIMEZONE`, `TAX_MODE`, discount mode, ZATCA, SMS, and notification toggles (all overridable per store from admin).
