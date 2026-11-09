# Product decisions

These come from the feature review with Liam on 9 Nov 2026. The full spec is the "Vault Nine — Astro + Laravel Rebuild Spec" doc on claude.ai.

## Competition types

| Type | Notes |
|---|---|
| Standard draw | Fixed ticket pool, drawn at `draw_at`. Can be **free to enter** (no payment, still needs an account and still counts towards capacity). |
| Instant win | Prize numbers pre-seeded at random. **End prize is optional.** Every ticket also goes into the end-prize draw when there is one. |

Both types have an **auto-draw toggle**. When it's on, the scheduler draws at the exact minute. When it's off, the draw is a live draw run from the admin presenter screen during the Facebook Live.

## Kept

- Sale price ("was" price)
- Bundle tiers (fixed-price packs, e.g. 10 for £2.50)
- Buy X get Y free
- Ticket multiplier (buy N or more and your end-prize entries are multiplied)
- Cash alternative (shown on the comp page)
- **Coming soon** status: comps are preloaded in admin and stay hidden from the public until staff set them live
- Random ticket allocation for every entry (paid and postal)
- Random prize-to-ticket mapping for instant wins
- Free postal entry on every comp

## Cut

- Player boards (and their auto draw)
- Scheduled go-live (staff set comps live by hand)
- Skill question: free postal entry is the legal route, and all "skill-based" copy is removed
- Bulk "dailies" creation
- Duplicate comp
- "Includes" bullet list
- Sequential ticket numbers
- Admin-entered winning ticket (every draw comes from the engine)
- Test and preview pages (use staging instead)
- Public `/docs/payments/cashflows` page

## Still to decide (ask Liam)

- Draws when sold out (as a third draw mode)?
- Featured/pinned comps: assumed **yes** (the home hero carousel uses `featured`)
- Buying flow, instant-win games, accounts, content and admin sections of the inventory (sections 2–7)
- Payment provider: keep Cashflows hosted checkout, or add Apple Pay / Google Pay?
- Per-person cap: none, or about 10%?
