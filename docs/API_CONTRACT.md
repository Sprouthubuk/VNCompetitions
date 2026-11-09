# API contract (v1)

The Astro storefront already consumes these endpoints through `web/src/lib/data.ts`. Responses are wrapped in `{ "data": ... }`. Field names are camelCase and match the `Comp` type in `web/src/data/comps.ts`. Build Laravel API Resources that produce exactly this shape.

Base URL: `API_URL` (e.g. `https://api.staging.vaultninecompetitions.co.uk`). All public endpoints are unauthenticated GETs and cacheable for 15–30 seconds.

## GET /api/v1/competitions

Returns live comps only (`status = live`; not `coming_soon`, not `draft`), soonest `drawAt` first.

```json
{
  "data": [
    {
      "slug": "pick-your-board",
      "title": "Pick your board",
      "brand": "Unicorn",
      "kind": "board",
      "type": "standard",
      "category": "Boards",
      "price": 0.29,
      "was": 0.49,
      "sold": 18,
      "max": 999,
      "drawAt": "2026-11-11T21:30:00Z",
      "drawMode": "live",
      "description": "Winner chooses a Unicorn 2.0, Target Tor, …",
      "cashAlternative": 120,
      "featured": true,
      "image": "https://cdn…/pick-your-board.webp",
      "bundles": [{ "tickets": 10, "price": 2.5 }, { "tickets": 25, "price": 5.75, "tag": "Popular" }],
      "buyX": { "buy": 10, "free": 2 },
      "multiplier": null,
      "endPrize": null
    }
  ]
}
```

Field notes:

| Field | Type | Notes |
|---|---|---|
| `kind` | `darts \| board \| ring \| box \| voucher` | Picks the fallback illustration when there's no `image` |
| `type` | `standard \| instant` | |
| `category` | `Instant win \| Darts \| Boards \| Mystery boxes \| Vouchers` | Admin-managed list later; keep these values for now |
| `price`, `was`, `cashAlternative` | number (pounds) | `was` only when a sale price is active |
| `sold` | int | Sold and held tickets plus postal entries: everything that uses up capacity |
| `drawMode` | `live \| auto` | From the auto-draw toggle |
| `free` | bool | Free-to-enter comp (add to the TS type when built) |
| `image` | string \| null | Not used by the storefront yet; add `image` support to `PrizeArt` when real images exist |

Instant-win comps also include:

```json
{
  "game": "wheel",
  "instantCount": 29999,
  "instantRemaining": 26026,
  "endPrize": "Target Omni home set-up",
  "instantPrizes": [
    { "name": "Target Omni auto-scorer", "tier": "Premium", "left": 1, "total": 1 },
    { "name": "£1 site credit", "tier": "Credit", "left": 20210, "total": 23000 }
  ]
}
```

`instantPrizes` is the **public ledger**: names and counts only. **Never** include winning ticket numbers or seed data.

## GET /api/v1/competitions/{slug}

Returns one comp in the same shape, or 404. `live` comps only, plus `closed`/`drawn` ones (so old links still work and can show the result).

## GET /api/v1/winners

The latest 50 announced winners.

```json
{ "data": [{ "name": "Craig B.", "prize": "Star Wars Target Mandalorian 26g", "ticket": "#143", "date": "08 Nov 2026" }] }
```

`name` is the first name plus the surname's initial. Never return the full surname or any contact detail.

## Next (not consumed yet)

The storefront still runs the basket in localStorage. Once checkout is built (build plan phase 2), these replace it:

| Method | Path | What it does |
|---|---|---|
| `POST` | `/api/v1/basket` / `GET` / `DELETE` | Server-side basket, session or user |
| `POST` | `/api/v1/checkout` | Re-price, hold tickets 15 min, return the Cashflows redirect URL or complete a credit-only order |
| `POST` | `/api/v1/webhooks/cashflows` | Signed; idempotent finalise |
| `GET` | `/api/v1/orders/{id}` | Status and ticket numbers, for the success/reveal page |
| `GET` | `/api/v1/me`, `/api/v1/me/entries`, `/api/v1/me/wallet`, `/api/v1/me/limits` | Account (Sanctum cookie auth) |
| `GET` | `/api/v1/draws/{id}` | Public draw proof: commit hash, seed (after reveal), entry-list hash, winner |
