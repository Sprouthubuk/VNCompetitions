# 05 — Business Logic (end to end)

File/function references point to `src/…`, `supabase/functions/<fn>/index.ts`, and DB functions documented verbatim in `03-database.md`. Where behaviour could not be confirmed from code it is marked **Unclear**.

---

## 1. Competition lifecycle

**Table:** `public.competitions`. Public reads go through view `competitions_public` (security_invoker; hides `quiz_answer`, `cost_price`, etc.).

**Statuses (text, not enum):** `scheduled` → `active` → `sold-out` → `ended` (also `coming-soon`, `completed`, `draft`-like states may exist in old rows — **Unclear**, check `SELECT DISTINCT status`). There is no explicit `draft` status; hiding is done with `scheduled`/`coming-soon`.

| Transition | Mechanism |
|---|---|
| create | Admin `AdminCompetitionForm.tsx` inserts row; trigger `set_competition_slug` generates unique slug via `slugify()` + `-2`, `-3` suffixes; trigger `force_sequential_for_instant_win` forces `random_ticket_allocation=false` when `is_instant_win`. |
| scheduled → active | pg_cron job 13 every 5 min: `UPDATE competitions SET status='active' WHERE status='scheduled' AND scheduled_start_at <= now()`. |
| active → sold-out | Trigger `check_competition_sold_out` (BEFORE UPDATE) when `entries_sold >= max_entries`. |
| entry cutoff | Strict at `draw_date` unless `draws_on_sellout=true` — enforced in `create-cashflows-checkout` and `enter_free_competition`; UI hides buy button. |
| → ended | Auto: `auto-draw-scheduled` (cron every 5 min) for auto-draw types; player boards via `player_board_auto_draw()` when all spaces sold. Manual: admin live draw (`draw-winner`) then status set ended. |

**Key fields:** `ticket_price`, `sale_price` (used when < ticket_price), `max_entries` (ticket pool size), `entries_sold` (counter maintained by trigger `handle_entry_payment_completed` and RPC `increment_entries_sold`), `draw_date` (UTC; UI shows UK time, 8 PM / 9:30 PM UK defaults), `is_free`, `is_instant_win`, `instant_win_game` (`classic`/`dartboard`/`wheel`), `competition_type` (`standard`, `player_board`, auto-draw types), `random_ticket_allocation`, `buy_quantity`/`free_quantity` (Buy X Get Y), `bundle_tiers` (jsonb price tiers), `multiplier_min_tickets`/`multiplier_value`, `exclude_from_discounts`, `cash_alternative_enabled`/`cash_alternative_value`, `draws_on_sellout`, `quiz_question`/`quiz_options`/`quiz_answer`, `featured`, `prize_purchased*`, `cost_price`, `max_entries_per_user` (now only used by player boards).

**Duplicate / Bulk dailies:** admin duplicates copy settings, reset counters, `prize_purchased`, keep `instant_win_game`.

---

## 2. Ticket purchase flow

1. **Basket:** `src/contexts/CartContext.tsx` stores items in localStorage (`competitionId`, `ticketCount`, type, `spaceIds`, `bundleTierIndex`). Quantity UI in `CompetitionDetail.tsx` (slider + quick chips, default 5) caps at remaining capacity (`src/lib/entryLimits.ts → remainingCompetitionTickets`). **No per-person cap** (removed Nov 2026; `MAX_ENTRIES_PER_PERSON = Infinity`).
2. **Checkout:** `CartSheet.tsx` → `supabase.functions.invoke('create-cashflows-checkout', { items, promoCode, creditAmount })`.
3. **Server validation** (`create-cashflows-checkout`):
   - Auth via bearer token (`auth.getUser`).
   - Items validated (UUID, positive integer counts). Competitions loaded **server-side** with prices.
   - Each comp must be `active`, before `draw_date` (unless draws_on_sellout).
   - **Capacity check:** `remaining = max_entries − entries_sold − held`, where `held` = tickets in other users' `pending_payment` orders from the last 30 minutes. Bonus tickets (Buy X Get Y / multiplier) counted. Rejects before payment if not enough.
   - **Pricing (server):** bundle tier price if `bundleTierIndex` matches a DB tier, otherwise `effectivePrice × ticketCount` where `effectivePrice = sale_price < ticket_price ? sale_price : ticket_price`. Bonus tickets from `src/lib/bundleDeals.ts` logic mirrored server-side.
   - **Promo:** looked up; min order checked; discount applied only to items not `exclude_from_discounts` and not in `excluded_competition_ids`; `consume_promo_code()` increments `times_used` **at checkout creation** (not on payment).
   - **Site credit:** `get_user_credit_balance`; `creditUsed = min(requested, available, total)`.
   - **Responsible play:** `check_user_spend_limit(user, amount)` → 403 if self-excluded / cool-off / over limit.
   - **Order:** inserts `orders` row `status='pending_payment'`, `metadata.items[]` (incl. `totalTickets`, bundle info), `creditUsed`, promo, influencer. Trigger `enforce_order_entry_limit` locks comps and validates positive counts.
   - **Zero-cash order** (fully paid by credit or free): entries created immediately, credit deducted, instant wins processed, returns results directly (no Cashflows).
   - **Otherwise:** POST Cashflows payment job (amount, order number, return URLs `SITE_BASE_URL/payment/success?order=…`), stores job reference + action URL, returns URL; browser redirects to Cashflows hosted page.
4. **Webhook** (`cashflows-webhook`): logs payload → finds order by `orderNumber` or job ref → **re-verifies with Cashflows API** → maps status (`paid`/`failed`/`cancelled`/…) → if `paid` and no completed entries exist for that `paymentReference`:
   - Inserts one `entries` row per item (`payment_status='completed'`, `payment_intent_id=paymentReference`, `ticket_count=totalTickets`, `amount_paid` = item share).
   - DB triggers on insert: `aa_shared_entry_limit`/`enforce_shared_entry_limit` (positive count, comp lock), `assign_ticket_numbers` (sequential) or `allocate_random_tickets_for_entry` (random), `handle_entry_payment_completed` (+entries_sold).
   - Saves `entry_ids` on order; deducts site credit (`deduct_site_credit`, idempotent per order).
   - Instant-win entries → `process_instant_win()` each; then sets `orders.metadata.instantWinProcessedAt`.
   - Sends emails (grouped: one instant-win email per instant-win competition, one entry confirmation for normal competitions).
   - Player-board items: mark spaces sold, create entries, email, run `player_board_auto_draw` if full.
5. **Confirmation:** `/payment/success` polls `order-status` (see §12).
6. **Failure / refunds:** failed/cancelled status stored on order; no entries created. `expire-stuck-orders` (hourly) marks pending orders >2h old as `expired` and frees reserved spaces. **There is no automated refund flow** — refunds are done manually in Cashflows and/or by granting site credit via admin.
7. **Race conditions when selling out:**
   - Pre-payment: capacity + 30-minute holds reduce oversell but two checkouts can still pass simultaneously if both read before either order is inserted (the order trigger locks the comp row but does not re-check capacity). **Unclear / partial.**
   - Post-payment: allocation functions lock the competition row (`FOR UPDATE`); random allocation raises `Not enough tickets remaining` if pool exhausted → that entry fails after the customer has paid (historic case: Lee Platt). Sequential allocation does **not** check the cap, so it can number past `max_entries`.

---

## 3. Ticket number allocation

- **Sequential** (default for instant-win; forced by trigger): `assign_ticket_numbers` BEFORE INSERT/UPDATE on `entries` when completed → `ticket_start_number = max(end of paid, end/legacy count of postal) + 1`; tickets are `start … start+count−1`. Postal entries: `assign_postal_ticket_numbers` the same way (shared numbering). Competition row is locked (`FOR UPDATE`) to serialise.
- **Random:** `allocate_random_tickets()` inserts rows into `entry_tickets` (unique `(competition_id, ticket_number)`), random probing with `random()` up to 40 attempts, then exhaustive shuffle fallback. Serialised by locking the competition row; uniqueness guaranteed by the unique index + `ON CONFLICT DO NOTHING`.
- Frontend display helpers: `src/lib/ticketNumbers.ts`.

## 4. Skill question

- Stored on `competitions.quiz_question`, `quiz_options[]`, `quiz_answer` (answer hidden from `competitions_public`).
- Validated client-side by calling RPC `validate_quiz_answer(comp, answer)` from `CompetitionDetail.tsx` before add-to-basket; wrong answer shows a warning and blocks adding; unlimited retries.
- **Not enforced at checkout** — `create-cashflows-checkout` does not receive or check the answer (see 09).

## 5. Instant wins

- **Seeding:** `instant_win_prizes` rows per competition, each with `winning_ticket_number`, `prize_name`, `prize_value`, `prize_type` (`site_credit` or physical/cash), `total_quantity`, `claimed_quantity`, `prize_tier`. Uploaded via `InstantWinPrizesEditor.tsx` (CSV/XLSX; losing tickets omitted). Admins can move prizes between ticket numbers mid-sale (no history kept).
- **Detection:** after payment, `process_instant_win(entry, comp, user)` finds unclaimed prizes whose `winning_ticket_number` falls in the entry's sequential range (or in its `entry_tickets` if random), ordered by tier, locks them (`FOR UPDATE`), increments `claimed_quantity`, inserts `instant_win_results (is_winner=true)`. Idempotent per entry.
- **Site credit prizes:** auto-granted into `site_credits` (`type='grant'`, expires +30 days). Physical prizes go to admin fulfilment (`AdminInstantWins.tsx`, `fulfillment_status`).
- Each ticket is its own outcome; reveal UI shows one per ticket (wins + explicit No Win).

## 6. Main draw

- **Live draws (manual, Facebook Live):** `/admin/live-draw` → `draw-winner` edge function builds full pool (completed paid entries + postal, sequential ranges or `entry_tickets`), picks via `crypto.getRandomValues()` (modulo; negligible bias), or confirms an admin-entered ticket. Writes `winners` (verification `pending`), `draw_audits` (seed, sha256 hash, totals, winner), emails winner. Presentation Mode animates the result (TicketRoller/WinnerReveal).
- **Auto draws:** `auto-draw-scheduled` (cron every 5 min) for due auto-draw competitions; same writes; up to 5 min late.
- **Player boards:** `player_board_auto_draw()` uses Postgres `ORDER BY random()`.
- **Publishing:** `winners.announced=true` makes results public (`/winners`, `get_announced_draw_results` for `/draw-results`); `draw_audits.is_published` for `/audit/:id`.
- **Winner verification:** user uploads photo ID (`upload_winner_id_document`, private bucket) within 48h; admin approves/rejects; prize dispatched flag.

## 7. Free postal entries

- Admin logs each envelope in `/admin/postal-entries` → `postal_entries` row with `ticket_count=1` (trigger enforces one per envelope + email required). Ticket numbers allocated by `assign_postal_ticket_numbers` (sequential, persisted in `ticket_start_number`) or `allocate_random_tickets_for_postal`. Same draw pool. **Note:** postal entries do **not** increment `entries_sold` (no trigger) — **Unclear if intended**; 0 rows currently.

## 8. Accounts, auth, profiles, age/UK

- Supabase email/password. Signup collects first/last name, phone, address, postcode (zod). Trigger `handle_new_user` creates `profiles` + `user_roles('user')`.
- **Age 18+ / UK residency: no DOB field or age checkbox was found in `Auth.tsx`**; compliance relies on T&Cs wording and ID verification at prize claim. Country is free text.
- Account deletion via `delete-user` edge function.

## 9. Site credit, promo codes, referrals

- **Ledger:** `site_credits` rows: `grant` (with `expires_at`) and `deduction` (with `order_id`).
- **Earned:** instant-win site-credit prizes (+30 days), admin grants (`AdminSiteCredits.tsx`, refunds/goodwill).
- **Spent:** at checkout (`creditAmount`) — deducted immediately for zero-cash orders, otherwise in webhook after payment (`deduct_site_credit`, locked, capped to balance, idempotent per order). Player boards same.
- **Balance:** `get_user_credit_balance()` = active grants − (deductions not attributed to expired grants) — an approximation of FIFO.
- **Expiry:** fixed 30 days from grant; expiry is computed at read time (no job).
- **Promo codes:** `promo_codes` (percentage/fixed, `max_uses`, `valid_until`, `min_order_amount`, `excluded_competition_ids`). Validated (`validate_promo_code`) for display, applied server-side, consumed at checkout creation. Linked to `influencers` (commission tracking via `orders.influencer_id`).
- **Referrals:** none implemented.

## 10. Responsible play

- `user_limits`: daily/weekly/monthly spend limits, `cool_off_until`, `self_excluded_until`. Set in Account → ResponsiblePlayPanel.
- Enforced server-side in `create-cashflows-checkout` and `player-board-checkout` via `check_user_spend_limit` (sums paid orders in rolling 1/7/30 days). Free entries (`enter_free_competition`) are **not** checked.
- Trigger `guard_user_limits_update` prevents users shortening an active cool-off/self-exclusion (admins can — but business policy is they must not).

## 11. Emails

| Type (send-email) | Trigger | Content |
|---|---|---|
| `entry_confirmation` | Webhook / zero-cash checkout, non-instant-win items | Order number, competitions, ticket numbers, draw date |
| `instant_win_confirmation` | Per instant-win competition after processing | That competition's tickets and wins only |
| `winner_notification` | `draw-winner`, `auto-draw-scheduled` | You won, verify ID within 48h |
| `player_board_confirmation` / `player_board_winner` | Player board purchase / draw | Spaces bought / winner |
| `draw_reminder` | `send-draw-reminders` cron hourly | Draw happening soon |
| `draw_complete` | After draws | Result summary to entrants (Unclear exact trigger) |
| `welcome` | After signup | Welcome |
| `marketing` | Admin (e.g. Identity Verified notice) | Free-form |
| `contact` | Contact form | Sent to fixed company inbox |
| `password_reset` | Legacy; auth emails now via `auth-email-hook` | — |
| Bulk campaigns | `send-bulk-email` | Admin-authored HTML to opted-in users |
| Auth emails | `auth-email-hook` | signup, recovery, magic link, invite, email change, reauth |

Templates are inline HTML in `send-email/index.ts` (full source in 04). All logged in `email_logs`.

## 12. Reveal animation ← order-status polling

1. Cashflows redirects to `/payment/success?order=<orderNumber>`.
2. `CashflowsPaymentSuccess.tsx` polls `GET functions/v1/order-status?orderNumber=` every 2s, max 15 polls (~30s), with the user's bearer token.
3. `order-status` returns `status`. If `paid` and the order has instant-win entries but `metadata.instantWinProcessedAt` is not set, it returns `instantWinPending: true` and the page keeps polling.
4. Once processed, it returns `instantWinResults[]`: one element per ticket — wins (prize name/value/type, ticket number) and explicit No Win — plus the competition's `instant_win_game`.
5. The page fires the Meta Purchase event, then shows results one at a time (`InstantWinReveal` → game from `registry.ts`; Classic 2s suspense, Dartboard or Wheel animate to the predetermined result), then a summary + Trustpilot.
6. If polling times out, status `timeout` → page tells the user the order is processing; results remain visible in Account.
The **browser never decides outcomes**; it only animates server results.

## 13. Webhook arrives late, twice, or never

- **Late:** order stays `pending_payment`; success page times out after ~30s with a "still processing" message; when the webhook eventually arrives entries are created normally (if within the cutoff and capacity). Capacity holds for that order last 30 minutes, after which other buyers may take those tickets → late allocation can fail (random) or overrun (sequential). If >2h, `expire-stuck-orders` marks it `expired`; a later webhook will still re-verify and set it `paid` and create entries (status overwritten) — **Unclear if intended**.
- **Twice:** idempotency check `entries WHERE payment_intent_id = paymentReference AND completed`. Sequential duplicates are skipped. **Not atomic**: two concurrent deliveries can both pass the check before either inserts → duplicate entries (no unique constraint on `payment_intent_id`). Credit deduction and instant wins are separately idempotent.
- **Never:** no reconciliation job polls Cashflows for pending orders. The order expires after 2h, customer has been charged but has no entries → must be fixed manually (webhook logs / Cashflows portal). `order-status` does not query Cashflows itself.

## 14. Player-board checkout

- Competitions with `competition_type='player_board'` have `competition_spaces` (numbered, named players). `usePlayerBoard.ts` shows the board.
- `player-board-checkout`: auth, expires stale reservations, validates spaces are available, enforces `max_entries_per_user` (spaces per person), reserves spaces (`status='reserved'`, `reserved_until`), applies promo (increments `times_used` directly, non-atomic), site credit, spend limits, creates order + Cashflows job; zero-cash path completes immediately.
- Webhook marks spaces `sold`, creates entries, emails, and if all spaces sold runs `player_board_auto_draw()` → winner + audit + email. Expired reservations returned by `expire_space_reservations()`.

## 15. Winners, results and entry list pages — data sources

- `/winners`: `winners` where `announced=true` (+ competitions for titles/images). Home carousel same.
- `/draw-results`: RPC `get_announced_draw_results()` (winning ticket only, no total).
- `/audit/:id`: `draw_audits` published rows; `verify_ticket_in_draw`.
- Instant-win prize lists on detail pages: `get_instant_win_prize_status()` (winner names masked "First L.").
- No public full entry list page exists; admins use `/admin/entries` and live draw ticket mapping.
