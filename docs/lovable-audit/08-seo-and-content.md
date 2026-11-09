# 08 — SEO & Content

## Rendering model

Client-side rendered SPA. Crawlers get `index.html` meta first; per-page meta is injected by `react-helmet-async` after JS runs. Moving to Astro (SSR/SSG) is a clear SEO win — render meta and JSON-LD server-side.

## Global meta (`index.html`)

- `<title>` "VaultNine Competitions | UK Darts Prize Competitions"; description; keywords; author; `theme-color #0F3D1F`; `robots index, follow`; Google Search Console verification meta; `format-detection telephone=no`; viewport with `viewport-fit=cover`.
- Open Graph: `og:type website`, `og:site_name`, `og:title`, `og:description`, `og:image https://vaultninecompetitions.co.uk/og-image.png` (1200×630), `og:locale en_GB`.
- Twitter: `summary_large_image`, `@VaultNineComps`, title/description/image.
- `<html lang>`: en-GB intended (verify).
- Scripts: Meta Pixel (ID 949586437580781), Mailchimp connected-site script.
- JSON-LD block (Organization / WebSite) in `index.html`.

## Per-page meta (`src/components/SEO.tsx`)

Props: title, description, canonical path, og image, optional JSON-LD. Renders `<title>` ("… | VaultNine Competitions"), description, canonical (`https://vaultninecompetitions.co.uk` + path), `og:title/description/type/image/locale/url`, Twitter tags, and `<script type="application/ld+json">`. Competition detail pages pass the competition image and Product/Event-style structured data (see `CompetitionDetail.tsx`).

## Sitemap & robots

- `scripts/generate-sitemap.ts` runs on `predev`/`prebuild`: static routes + one `/competition/<slug>` per competition with status `active|coming-soon|sold-out`, fetched from the public REST API (hardcoded URL and anon key — [REDACTED]). Writes `public/sitemap.xml`.
- `public/robots.txt`: allows Googlebot, Bingbot, Twitterbot, facebookexternalhit, all; disallows `/admin/`, `/payment/`, `/auth`, `/reset-password`, `/account`, `/docs/`. References `Sitemap: https://vaultninecompetitions.co.uk/sitemap.xml`.
- `public/llms.txt` for AI crawlers.

## URL / slug logic

- DB: `slugify(text)` lowercases, replaces non `[a-z0-9]` runs with `-`, trims dashes. `generate_unique_competition_slug(title,id)` appends `-2`, `-3`… on collision. Trigger `set_competition_slug` fills `slug` on insert/update if empty. Slugs are **not** regenerated when titles change.
- Route `/competition/:id` accepts slug or UUID; UUID URLs `navigate(replace)` to the slug URL.
- Legacy/alias redirects in `App.tsx` (see 02).

## Static content locations

All content is hardcoded JSX in page files (no CMS):

| Content | File |
|---|---|
| Terms & Conditions (competition rules, postal entry, random allocation, cash alternative; last updated 30 Oct 2026) | `src/pages/Terms.tsx` |
| Website terms of use | `src/pages/TermsOfUse.tsx` |
| Acceptable use | `src/pages/AcceptableUse.tsx` |
| Privacy policy | `src/pages/Privacy.tsx` |
| Cookies | `src/pages/Cookies.tsx` |
| FAQ | `src/pages/FAQ.tsx` |
| Free postal entry (address, rules) | `src/pages/PostalEntry.tsx` |
| How it works | `src/pages/HowItWorks.tsx`, `src/components/home/HowItWorksSection.tsx` |
| About | `src/pages/About.tsx` |
| Contact | `src/pages/Contact.tsx` |
| Responsible play | `src/pages/ResponsiblePlay.tsx` |
| Footer (links, socials, Visa/Mastercard logos) | `src/components/layout/Footer.tsx` |
| Email copy | `supabase/functions/send-email/index.ts`, `_shared/email-templates/*` |
| Cashflows notes | `src/pages/CashflowsDocs.tsx` |

Language rule: always "prize competition", never "lottery"/"gamble"; free postal route must be offered.
