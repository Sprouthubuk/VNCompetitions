# 10 — Data Migration

Row counts at export: see `03-database.md` → Row counts. Highlights: profiles 2,143; orders 11,468; entries 18,256; entry_tickets 62,398; instant_win_prizes 156,753; instant_win_results 13,786; site_credits 11,008; winners 457; draw_audits 457; competitions 481.

## Must migrate (live business data)

| Table | Why | Notes |
|---|---|---|
| `auth.users` | Logins | Export id, email, `encrypted_password` (bcrypt — Laravel `Hash::check` can verify `$2a$` bcrypt), `email_confirmed_at`, `created_at`, `raw_user_meta_data`. **Keep UUIDs** — every table references them. Requires DB-level export (not via Data API). |
| `profiles` | Names, phone, address, marketing opt-in | 1:1 with auth.users by `user_id` |
| `user_roles` | Admin flags | Only `admin` rows matter |
| `user_limits` | Responsible play (legal obligation) | Active cool-offs/self-exclusions must carry over exactly |
| `competitions` | Catalogue + history | Re-host `image_url` images (Supabase storage + Lovable CDN URLs) |
| `orders` | Payments ledger | `metadata` jsonb holds items, creditUsed, instantWinProcessedAt; Cashflows refs |
| `entries` | Purchases / free entries | `ticket_start_number` (sequential), `payment_intent_id` |
| `entry_tickets` | Random ticket numbers | Required to reproduce random-allocation draws |
| `postal_entries` | Free entries | 0 rows now, keep structure |
| `instant_win_prizes` | Prize seeds incl. future unclaimed winning tickets | Confidential — treat as secret data |
| `instant_win_results` | Wins & fulfilment | |
| `site_credits` | Customer balances (liability) | Keep grants with `expires_at` and deductions with `order_id`; verify balances match `get_user_credit_balance` after import |
| `winners` | Winners, verification status | `id_document_url` = path in private bucket |
| `draw_audits` | Draw evidence | Keep immutable going forward |
| `competition_spaces` | Player boards | |
| `promo_codes`, `influencers` | Marketing / commissions | |
| `announcement_bar` | Config | 1 row |

**Optional / can be archived:** `email_logs` (90-day retention), `webhook_logs` (30-day retention), `stats_cache` (rebuildable — do not migrate).

**Storage:** `winner-id-documents` (private, ~413 MB — consider retention/deletion policy first), `competition-images` (prune ~311 MB orphans), `email-assets`. Plus Lovable CDN assets referenced in code/DB.

## Relationships

```text
auth.users 1─1 profiles, 1─n user_roles, 1─1 user_limits
auth.users 1─n orders 1─n entries (via orders.entry_ids text[] — NOT a FK; also entries.payment_intent_id = orders.cashflows_payment_reference)
competitions 1─n entries 1─n entry_tickets
competitions 1─n postal_entries 1─n entry_tickets
competitions 1─n instant_win_prizes 1─n instant_win_results n─1 entries
competitions 1─n winners, draw_audits, competition_spaces
orders 1─n site_credits (deductions), competition_spaces
promo_codes 1─n influencers; influencers 1─n orders
```

## Data quality issues to handle

- `orders.entry_ids` and `orders.competition_ids` are `text[]` arrays, not FKs; normalise into an `order_items` / pivot table.
- `user_id` columns on `site_credits`, `orders`, `instant_win_results`, `user_limits`, `draw_audits.performed_by_admin_id` are plain UUIDs with no FK; check for orphans (deleted users).
- `draw_audits.performed_by_admin_id` uses `00000000-…` for automatic draws.
- `competitions.status` is free text — run `SELECT DISTINCT status` and map to an enum.
- `entries_sold` is denormalised; recompute = SUM(completed entries.ticket_count) (+ postal if desired) and compare.
- Some old entries may be sequential with `ticket_start_number` NULL (legacy); postal legacy rows also.
- Known incident rows: a competition (Luke Littler Nuke Fallout, 11 Nov) has a winner record dated before its current draw date — review duplicates/relaunched comps.
- Test entries were deleted historically; abandoned `pending_payment`/`expired` orders exist in volume (hundreds).
- `promo_codes.code` comparisons are case-insensitive — enforce a unique lower-case index.
- Money in `numeric` GBP pounds (not pence) — keep decimal(10,2).
- Timestamps are `timestamptz` (UTC); UI displays Europe/London.

## Recommended export order

1. `auth.users` (+ password hashes) → `profiles` → `user_roles` → `user_limits`
2. `promo_codes` → `influencers`
3. `competitions` (+ images)
4. `competition_spaces` (without order/entry refs, update after)
5. `orders`
6. `entries`
7. `postal_entries`
8. `entry_tickets`
9. `instant_win_prizes` → `instant_win_results`
10. `site_credits`
11. `winners` (+ ID documents) → `draw_audits`
12. Back-fill `competition_spaces.order_id/entry_id`, `orders.entry_ids` pivot
13. `announcement_bar`; optionally `email_logs`, `webhook_logs`
14. Verify: per-competition ticket counts, `entries_sold`, credit balances per user, winner counts, revenue totals vs `admin_competition_report`.

Freeze sales (no live competitions, cron jobs disabled) during the final delta export to avoid drift between orders, entries and Cashflows.
