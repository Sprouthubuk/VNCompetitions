# Build plan

The engines come first and the storefront last: money and tickets are proven by tests before any screen depends on them. Each step ends with checks that must pass before moving on. Commit at the end of every step.

The storefront (`web/`) already exists and reads `API_URL`, so as soon as step 4 is deployed the staging site shows real data.

---

## Phase 1: foundations

### Step 1. Create the Laravel app in `api/`

```bash
composer create-project laravel/laravel api   # Laravel 12, PHP 8.3+
cd api
php artisan install:api                       # Sanctum + routes/api.php
composer require filament/filament laravel/horizon spatie/laravel-permission
composer require --dev pestphp/pest pestphp/pest-plugin-laravel larastan/larastan laravel/pint
php artisan filament:install --panels         # panel id "admin", path /admin
php artisan horizon:install
```

- **`.env`:**
  - Postgres for `DB_*` (local: Postgres 16 in Docker or Herd).
  - `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`, `SESSION_DRIVER=redis`.
  - `APP_TIMEZONE=UTC`.
  - `FRONTEND_URL=http://localhost:4321`.
- **`config/cors.php`:** allow `FRONTEND_URL` for `api/*`; `supports_credentials=true` (needed later for Sanctum cookies).
- **Code checks:** Pint (PSR-12) and Larastan level 6.
- **GitHub Actions** (`.github/workflows/ci.yml` at the repo root):
  - **api job:** Postgres service, `composer install`, `pint --test`, `phpstan`, `php artisan test`.
  - **web job:** `npm ci`, `npm run build`.

✅ Check: `php artisan test` passes, `/admin` shows the Filament login, and CI is green.

### Step 2. Schema

UUID primary keys (`HasUuids`) everywhere, so Supabase IDs can be imported unchanged. Money is `decimal(10,2)`. Timestamps are `timestampTz`. Put the enums in `app/Enums`.

| Table | Key columns | Constraints |
|---|---|---|
| `users` | name, email, password, date_of_birth, phone, address fields, `country` (GB), `age_confirmed_at`, `marketing_email`, `marketing_sms`, `is_admin` | unique email |
| `competitions` | slug, title, brand, `kind` enum, `type` enum (standard, instant), `category`, description, `status` enum (**draft, coming_soon, live, sold_out, closed, drawn, cancelled**), `is_free`, `price`, `sale_price` null, `cash_alternative` null, `max_tickets`, `draw_at`, `auto_draw` bool, `featured` bool, `sort`, `image_path`, `end_prize` null, `game` enum (classic, dartboard, wheel) null, `prizes_locked_at` null, `prize_commitment` (sha256) null, `cost_price`, `prize_purchased` bool, `seo_title`, `seo_description` | unique slug; check `max_tickets > 0`; check `price >= 0`; check `sale_price < price` |
| `competition_bundles` | competition_id, tickets, price, tag null, sort | unique (competition_id, tickets) |
| `competition_offers` | competition_id, `kind` (buy_x_get_y, multiplier), buy / free (for BXGY), from / times (for multiplier) | one of each kind per comp |
| `instant_win_prizes` | competition_id, name, tier (Premium, Darts, Accessories, Credit), `credit_amount` null, `ticket_number`, `won_by_ticket_id` null, `won_at` null | unique (competition_id, ticket_number); **never** serialised publicly |
| `orders` | user_id, status (pending, paid, failed, expired, refunded), subtotal, discount, credit_used, total, `payment_reference` null, promo_code_id null, `held_until` | unique payment_reference |
| `order_items` | order_id, competition_id, quantity (paid), bonus_tickets, unit_price, line_total, bundle_id null | |
| `tickets` | competition_id, `number`, status (held, sold, postal, free), order_item_id null, postal_entry_id null, user_id, `held_until` null, `multiplier` int default 1 | **unique (competition_id, number)**; check number between 1 and max |
| `postal_entries` | competition_id, name, address, email, received_at, keyed_by, user_id null | |
| `instant_win_results` | ticket_id, instant_win_prize_id null, revealed_at null | unique ticket_id |
| `draws` | competition_id, kind (end_prize, standard), `entries_hash`, `seed_commitment`, `seed` null until revealed, `public_entropy`, `winning_ticket_id`, drawn_at, drawn_by | append-only: no update/delete in app code; DB trigger blocks UPDATE after `seed` is set |
| `winners` | competition_id, user_id, ticket_id, prize_text, display_name, photo_path, announced_at, verified_at | |
| `wallet_transactions` | user_id, amount (signed), type (prize, refund, goodwill, spend, expiry, reversal), balance_after, expires_at null, source_type/source_id | append-only |
| `responsible_limits` + `limit_events` | daily/weekly/monthly spend, cool_off_until, excluded_until | |
| `promo_codes` | code (lower-case), percent / fixed, min_order, max_uses, uses, expires_at, excluded competition ids (pivot), influencer tag | |
| `admin_audit_log` | admin_id, action, subject_type/id, before, after | |

✅ Check: `php artisan migrate:fresh` works. A Pest test proves that two inserts of the same `(competition_id, number)` fail at the DB.

### Step 3. Models, factories and seed data

- Eloquent models with casts (enums, decimals as strings or brick/money), relationships, and the scopes `Competition::live()` and `public()`.
- Factories for every model.
- `DatabaseSeeder`: an admin user (from env), plus the 8 sample comps from `web/src/data/comps.ts` with their bundles, offers and instant prize lines, so staging looks identical to the design preview.

### Step 4. Public API (matches `docs/API_CONTRACT.md` exactly)

- Routes under `/api/v1`, using `CompetitionResource` and `WinnerResource`.
- Cache responses for 15 seconds in Redis, tagged per comp, and bust the cache on admin save and ticket sale.
- Pest tests:
  - each endpoint's JSON shape matches the contract;
  - `coming_soon` and `draft` comps are invisible;
  - the instant-win payload never contains `ticket_number`.

✅ Check: run the storefront with `API_URL=http://localhost:8000 npm run dev`. Every page renders from the API.

### Step 5. Filament admin, first pass

- **CompetitionResource** form sections:
  - Basics (title, auto slug, type, category, kind, description, image, featured).
  - Pricing (price, sale price, free toggle, cash alternative, bundles repeater, Buy X Get Y, multiplier).
  - Draw (draw_at, auto-draw toggle, end prize).
  - Instant prizes (repeater: name, tier, quantity, credit amount).
- **Status actions:**
  - **Save as coming soon** (hidden).
  - **Go live:**
    1. Validate.
    2. Generate the instant-win numbers with `random_int()`: distinct numbers across `1..max_tickets`, one row per unit of prize quantity.
    3. Set `prizes_locked_at`.
    4. Store `prize_commitment` = sha256 of the sorted `number:prize_id` list plus a secret salt.
    5. Publish the commitment on the comp page.
  - **Close.**
  - Locked prizes are read-only after go-live, unless a reason is given (and the change is logged).
- Resources for Users (read-mostly), Winners, Postal entries (key-in form, duplicate warning), Orders (read-only for now).
- Roles with spatie/laravel-permission: Owner, Operator, Finance, Support.
- An `AuditLog` observer writes every admin create, update or delete to `admin_audit_log`.

### Step 6. Staging on Forge

Follow `docs/FORGE.md`. Set `API_URL` on the web site to the API site.

✅ Check: staging.vaultninecompetitions.co.uk shows comps created in `/admin`.

---

## Phase 2: engines (each one is a service class in `app/Services` with Pest tests)

1. **AllocationService:**
   - Lock the competition row (`SELECT … FOR UPDATE`) and count used capacity (held + sold + postal + free).
   - Pick N distinct random free numbers. Use `random_int` over the free set: load used numbers as a bitmap. For big comps, sample and retry on conflict.
   - Insert the rows. A unique-constraint violation means retry, never oversell.
2. **PricingService:**
   - Sale price, bundle tiers, Buy X Get Y bonus, multiplier, promo code, wallet credit.
   - Pure functions with exhaustive tests. The server re-prices every order and never trusts client totals.
3. **CheckoutService:**
   - Limits check (free entries included).
   - Hold tickets for 15 minutes.
   - Order total 0 → finalise immediately. Otherwise, create a Cashflows hosted payment and return the redirect URL.
4. **Cashflows webhook + ReconcilePendingOrders job:**
   - Verify the signature, then re-check status with the Cashflows API.
   - `finalise()` runs in one transaction: held → sold, wallet deduction, instant-win resolution, promo use. It's idempotent on `payment_reference`.
   - Every 5 minutes, ask Cashflows about orders pending for more than 10 minutes.
   - If an order is paid but its holds expired and the comp is full, refund it to the wallet automatically and send an email.
   - `ReleaseExpiredHolds` runs every minute.
5. **InstantWinService:** on finalise, match each sold ticket number against that comp's prize numbers, and write `instant_win_results`. Credit prizes go to the wallet with a 30-day expiry.
6. **WalletService:** append-only ledger, FIFO spend by expiry date, and an `ExpireCredit` daily job.
7. **LimitsService:**
   - Daily/weekly/monthly spend limits, cool-off and self-exclusion.
   - Increases take effect after 24 hours; decreases apply immediately.
8. **DrawService (Verity):**
   1. At close, freeze the entries and store the `entries_hash`.
   2. Commit `seed_commitment = sha256(seed)` before the draw.
   3. Winner index = `HMAC-SHA256(seed, public_entropy)` mod pool size, where `public_entropy` is a drand round fetched at draw time.
   4. Reveal the seed afterwards. `/api/v1/draws/{id}` exposes everything, so anyone can re-run it.
   - `auto_draw` comps are drawn by the scheduler at the exact `draw_at` minute.
   - Live draws use an admin presenter page.
   - The multiplier counts as extra weight for the end-prize draw.
   - Note: Liam has an existing Verity build. Reuse its algorithm if it's compatible.

✅ Exit check: a load test with 500 concurrent buyers on a 1,000-ticket comp gives zero oversell and zero duplicate numbers.

## Phase 3: admin

Presenter mode for live draws, a finance report per comp (revenue, credit used, prize cost, Cashflows fee of 1.9% + 20p, margin), a fulfilment board for physical prizes, a wallet admin, and dashboard alerts (stuck orders, comps under 40% sold 24 hours before their draw).

## Phase 4: storefront

- **Basket and checkout:** replace the localStorage basket in `web/src/layouts/Base.astro` with the API basket.
- **New pages:** account area (entries, Reveal Room, wallet, limits, ID upload), checkout return and reveal page, `/audit/{id}` draw proof.
- **Pages to move off the design:**
  - Free entry: currently a design page.
  - Legal pages: move into a Filament CMS.
- **SEO and tracking:** JSON-LD, sitemap from the DB, Meta CAPI, Klaviyo.
- **301 redirects** from the old Lovable URLs: `/competition/{slug}` → `/comp-{slug}`. Or rename the storefront route to `/competition/[slug]`, which is the better choice: one less redirect.

## Phase 5: migration and cutover

- **Import command:** `php artisan v9:import {dump}` is repeatable. Run it in dependency order (see `docs/lovable-audit/10-data-migration.md`).
- **Passwords:** Supabase's bcrypt `$2a$` hashes import as they are, so customers keep their passwords.
- **Ticket ranges:** expand sequential ranges into ticket rows.
- **Wallet:** replay `site_credits`.
- **Verify:** compare counts and balances against Supabase; the command fails on any mismatch.
- **Cutover:** after a Wednesday draw. Freeze sales, run the final delta import, switch the Cashflows webhook and DNS, and smoke-test a £0.10 order end to end.
