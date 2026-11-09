# 09 — Known Issues, Risks & Things Not To Carry Over

Severity: **High** (money/fairness/data exposure), **Med**, **Low**.

## A. Client trust — prices, ticket numbers, instant-win outcomes, spend limits

| Area | Is the client trusted? | Detail |
|---|---|---|
| Prices | **No** (good) | `create-cashflows-checkout` / `player-board-checkout` load `ticket_price`, `sale_price`, `bundle_tiers` from the DB and compute totals server-side. Client sends only competition IDs, counts, optional `bundleTierIndex` (validated against DB tiers), promo code and requested credit (capped to balance). |
| Ticket numbers | **No** (good) | Assigned by DB triggers (`assign_ticket_numbers`, `allocate_random_tickets`). `entry_tickets` has no client write grants; `entries` direct INSERT was removed. |
| Instant-win outcomes | **Mostly no** | Decided by `process_instant_win()` in the webhook. **But `process_instant_win` is EXECUTE-able by anon/authenticated**; it checks ownership and is idempotent per entry, so a user can only trigger processing of their own already-paid entry (low impact). Games only animate server results. |
| Spend limits | **No at checkout** | `check_user_spend_limit` is enforced in both checkout functions. **Gap:** `enter_free_competition` ignores responsible-play limits/cool-off (free, but self-excluded users can still enter). |
| Skill question | **Yes — High (compliance)** | Answer is only checked client-side (`validate_quiz_answer` before add-to-basket). Checkout never checks it, so the API can be called with no/incorrect answer. For a UK prize competition relying on skill, enforce server-side. |
| Basket counts vs capacity | Partially | UI caps by remaining tickets; server re-checks capacity before payment. |

## B. Security definer functions callable by anyone (anon)

`increment_entries_sold`, `consume_promo_code`, `player_board_auto_draw`, `expire_space_reservations`, `validate_quiz_answer`, `validate_promo_code`, `process_instant_win`, plus `admin_*` (these check `has_role` internally) — all have EXECUTE granted to `anon`.
- **High: `increment_entries_sold(comp, n)`** has no auth check — anyone can inflate `entries_sold`, flipping competitions to `sold-out` and blocking sales.
- **Med: `consume_promo_code(code)`** — anyone can burn a code's `max_uses`.
- **Med: `player_board_auto_draw(comp)`** — anyone can trigger a draw once all spaces are sold (would happen anyway, but timing is uncontrolled).
- **Low: `validate_quiz_answer`** — enables brute forcing (options are few anyway).
In Laravel, put all of these behind server-side services only.

## C. Payments & webhooks

- **High:** Webhook idempotency is check-then-insert with no unique constraint on `entries.payment_intent_id` → concurrent duplicate deliveries can double-allocate.
- **High:** No reconciliation for missing webhooks: paid-but-never-notified orders expire after 2h with no entries; manual fix only.
- **Med:** Inbound webhook has no signature/HMAC verification (mitigated by re-verifying with Cashflows API; still allows log spam and forced re-verification calls).
- **High:** Overselling: pre-payment capacity check is not atomic with order creation; sequential allocation does not enforce `max_entries`; random allocation throws after payment (paid customer gets no tickets).
- **Med:** Promo `times_used` consumed at checkout creation, not on payment; abandoned checkouts burn uses. Player-board checkout increments promo uses non-atomically.
- **Med:** Expired orders can later flip to `paid` if a late webhook arrives (status overwritten) — confirm desired.
- **Low:** No automated refunds; refunds done in Cashflows portal or as site credit.

## D. Fairness / audit (raised to client previously)

- Draw audits are editable by admins (not append-only); no immutable change log.
- Instant-win prizes can be moved between ticket numbers mid-sale; no history (`instant_win_prizes` has no audit table).
- Live draw allows admin-supplied ticket confirmation without enforced binding/reason.
- Seeds are generated/recorded after selection (not a commit-reveal); auto-draw seed is a label.
- Random allocation and player-board draws use Postgres `random()` (not cryptographic).
- Recommend: append-only audit tables, prize lock after sale starts, commit-reveal seeds, CSPRNG everywhere.

## E. Data exposure / RLS

- `stats_cache` has RLS with zero policies (intentional; only definer functions use it).
- Several tables previously flagged with broad policies; re-review every policy in `03-database.md` before re-implementing. Public view `competitions_public` uses `security_invoker` and depends on `competitions` SELECT grants to anon.
- Winner ID documents: private bucket + signed URLs (good). 413 MB of ID images — retention policy needed (GDPR).
- `/docs/payments/cashflows` page is publicly reachable (robots-disallowed only).

## F. Compliance gaps

- No 18+ declaration or DOB captured at signup (`Auth.tsx`); relies on T&Cs and ID at claim.
- No UK residency validation (country free text).
- Free entry (`enter_free_competition`) bypasses responsible-play limits.

## G. Operational / performance

- Cron job 9 is named "every-min" but runs every 5 min; auto-draws may be up to 5 minutes late.
- `publish-scheduled-competitions` edge function is dead (replaced by SQL cron job 13).
- `mailchimp-sync` + Mailchimp script are legacy (marketing moved to Brevo).
- `send-email` contains an open contact path (fixed recipient; spam risk, no captcha).
- Disk IO pressure history: large `cron.job_run_details` (truncated), `instant_win_prizes` is 156k rows — index on `(competition_id, winning_ticket_number)` needed in new DB.
- ~311 MB orphaned images in `competition-images` bucket.
- Lovable CDN image URLs (`/__l5e/assets-v1/...`) will break after leaving Lovable hosting — download and re-host; update `competitions.image_url` values that point there.
- `entries_sold` is a denormalised counter (trigger + RPC); postal entries do not increment it. Recompute from source tables after migration.

## H. Dead code / hacks / test pages

- `/scratch-test` (prototype), `/admin/instant-win-test`, `/admin/live-draw-test`, `/admin/instant-win-preview`, `/admin/data-export` (temporary).
- `enforce_one_free_entry_per_user()` is a no-op kept for trigger compatibility; `competition_person_entry_count` / `competition_person_reserved_count` / `get_my_competition_entry_count` remain from the removed 50-entry cap.
- `max_entries_per_user` column still used only by player boards.
- `src/data/competitions.ts` — likely static/mock data (Unclear if referenced).
- `src/integrations/supabase/types.ts` is generated — regenerate equivalents from the new schema.
- No automated tests.
- No TODO/FIXME markers found in code.
