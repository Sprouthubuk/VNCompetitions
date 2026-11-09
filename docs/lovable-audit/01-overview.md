# 01 — Overview

VaultNine Competitions — UK darts prize-competition site (vaultninecompetitions.co.uk). Single-page React app + Lovable Cloud (Supabase: Postgres, Auth, Storage, Edge Functions, pg_cron).

## Tech stack

- **Frontend:** React 18.3, TypeScript 5.8, Vite 5.4 (SWC plugin), React Router 6.30 (client-side routing, all routes in `src/App.tsx`), TanStack Query 5 (data cache; 60s staleTime, `src/lib/queryCache.ts`), Tailwind CSS 3.4 + shadcn/ui (Radix primitives), framer-motion 12, react-hook-form + zod, react-helmet-async (per-page meta), Embla carousel, Recharts (admin charts), jsPDF + autotable (PDF reports), SheetJS `xlsx` (instant-win spreadsheet import / exports), date-fns 3.
- **Backend:** Supabase (via Lovable Cloud) — PostgreSQL 15+ with RLS, security-definer RPC functions, triggers; Supabase Auth (email + password); Storage (3 buckets); 14 Deno edge functions; pg_cron + pg_net for scheduling.
- **Build scripts:** `predev`/`prebuild` run `scripts/generate-sitemap.ts` (writes `public/sitemap.xml`).
- **Tests:** no `*.test.*` files were found in `src/` or `supabase/` at export time — there is effectively no automated test coverage.
- **Dev-only/unused:** `drizzle-kit`, `drizzle-orm`, `postgres` dev deps are Lovable migration tooling, not used by app code. `lovable-tagger` is a Lovable dev plugin (remove when migrating).

### dependencies (package.json)

| Package | Version |
|---|---|
| @fontsource/anton | ^5.2.7 |
| @fontsource/hanken-grotesk | ^5.2.8 |
| @fontsource/jetbrains-mono | ^5.2.8 |
| @fontsource/outfit | ^5.2.8 |
| @hookform/resolvers | ^3.10.0 |
| @radix-ui/react-accordion | ^1.2.11 |
| @radix-ui/react-alert-dialog | ^1.1.14 |
| @radix-ui/react-aspect-ratio | ^1.1.7 |
| @radix-ui/react-avatar | ^1.1.10 |
| @radix-ui/react-checkbox | ^1.3.2 |
| @radix-ui/react-collapsible | ^1.1.11 |
| @radix-ui/react-context-menu | ^2.2.15 |
| @radix-ui/react-dialog | ^1.1.14 |
| @radix-ui/react-dropdown-menu | ^2.1.15 |
| @radix-ui/react-hover-card | ^1.1.14 |
| @radix-ui/react-label | ^2.1.7 |
| @radix-ui/react-menubar | ^1.1.15 |
| @radix-ui/react-navigation-menu | ^1.2.13 |
| @radix-ui/react-popover | ^1.1.14 |
| @radix-ui/react-progress | ^1.1.7 |
| @radix-ui/react-radio-group | ^1.3.7 |
| @radix-ui/react-scroll-area | ^1.2.9 |
| @radix-ui/react-select | ^2.2.5 |
| @radix-ui/react-separator | ^1.1.7 |
| @radix-ui/react-slider | ^1.3.5 |
| @radix-ui/react-slot | ^1.2.3 |
| @radix-ui/react-switch | ^1.2.5 |
| @radix-ui/react-tabs | ^1.1.12 |
| @radix-ui/react-toast | ^1.2.14 |
| @radix-ui/react-toggle | ^1.1.9 |
| @radix-ui/react-toggle-group | ^1.1.10 |
| @radix-ui/react-tooltip | ^1.2.7 |
| @supabase/supabase-js | ^2.89.0 |
| @tanstack/react-query | ^5.83.0 |
| class-variance-authority | ^0.7.1 |
| clsx | ^2.1.1 |
| cmdk | ^1.1.1 |
| date-fns | ^3.6.0 |
| embla-carousel-react | ^8.6.0 |
| framer-motion | ^12.23.26 |
| input-otp | ^1.4.2 |
| jspdf | ^4.2.1 |
| jspdf-autotable | ^5.0.7 |
| lucide-react | ^0.462.0 |
| next-themes | ^0.3.0 |
| react | ^18.3.1 |
| react-day-picker | ^8.10.1 |
| react-dom | ^18.3.1 |
| react-helmet-async | ^2.0.5 |
| react-hook-form | ^7.61.1 |
| react-resizable-panels | ^2.1.9 |
| react-router-dom | ^6.30.1 |
| recharts | ^2.15.4 |
| sonner | ^1.7.4 |
| tailwind-merge | ^2.6.0 |
| tailwindcss-animate | ^1.0.7 |
| vaul | ^0.9.9 |
| xlsx | ^0.18.5 |
| zod | ^3.25.76 |

### devDependencies

| Package | Version |
|---|---|
| @eslint/js | ^9.32.0 |
| @tailwindcss/typography | ^0.5.16 |
| @types/node | ^22.16.5 |
| @types/react | ^18.3.23 |
| @types/react-dom | ^18.3.7 |
| @vitejs/plugin-react-swc | ^3.11.0 |
| autoprefixer | ^10.4.21 |
| drizzle-kit | 0.x |
| drizzle-orm | 0.x |
| eslint | ^9.32.0 |
| eslint-plugin-react-hooks | ^5.2.0 |
| eslint-plugin-react-refresh | ^0.4.20 |
| globals | ^15.15.0 |
| lovable-tagger | ^1.1.13 |
| postcss | ^8.5.6 |
| postgres | ^3.4.9 |
| tailwindcss | ^3.4.17 |
| typescript | ^5.8.3 |
| typescript-eslint | ^8.38.0 |
| vite | ^5.4.19 |

## Third-party services & integrations

| Service | Purpose | Where / how |
|---|---|---|
| **Cashflows** (Hosted Checkout) | Card payments (Visa/Mastercard). No Stripe anywhere. | `create-cashflows-checkout` and `player-board-checkout` create a payment job (`POST {base}/api/gateway/payment-jobs`, headers `ConfigurationId` + SHA-512 `Hash` of API key + body) and redirect the browser to the returned action URL. `cashflows-webhook` receives notifications and re-verifies via `GET /payment-jobs/{ref}/payments/{paymentRef}`. Return pages: `/payment/success|failed|cancelled`. Env: `CASHFLOWS_API_KEY`, `CASHFLOWS_CONFIGURATION_ID`, `CASHFLOWS_ENV`, `CASHFLOWS_BASE_URL`. |
| **Resend** | Transactional + bulk email | `send-email` (all transactional types), `send-bulk-email` (campaigns). Env: `RESEND_API_KEY`, `FROM_EMAIL`. Logged to `public.email_logs`. |
| **Lovable email (auth hook)** | Auth emails (signup confirm, recovery, magic link, invite, email change, reauth) | `auth-email-hook` with React-email templates in `supabase/functions/_shared/email-templates`. Platform-managed signing secret. |
| **Supabase Auth** | Email/password accounts | `src/contexts/AuthContext.tsx`, `src/pages/Auth.tsx`, `src/pages/ResetPassword.tsx`. No social/OAuth providers in use. HIBP leaked-password protection enabled (per project notes; verify in new auth system). |
| **Supabase Storage** | Images & ID documents | Buckets `competition-images` (public), `email-assets` (public), `winner-id-documents` (private, signed URLs). |
| **Lovable CDN assets** | Many competition/brand images | URLs of form `https://vaultninecompetitions.co.uk/__l5e/assets-v1/<uuid>/<file>`; pointers in `src/assets/*.asset.json`. **These URLs stop working once off Lovable hosting — download and re-host.** |
| **Meta Pixel** | Ads tracking | Pixel ID `949586437580781` in `index.html` + `src/lib/metaPixel.ts` (PageView, AddToCart, Purchase on `/payment/success`). Client-side only; no Conversions API, no advanced matching. |
| **Mailchimp** | Legacy audience sync + site connector script | `index.html` loads `chimpstatic.com/mcjs-connected/...` script; `mailchimp-sync` edge function (env `MAILCHIMP_API_KEY`, `MAILCHIMP_AUDIENCE_ID`) called from AuthContext/Account/AdminEmailCampaigns. Contacts are now exported to Brevo manually. |
| **Trustpilot** | Review collection widget | `src/components/trustpilot/TrustpilotCollectWidget.tsx` on payment success page; footer link. |
| **Google Search Console** | Site verification | `google-site-verification` meta tag in `index.html`. |
| **Facebook Live** | Live draws (operational, not code) | Admin Presentation Mode in `/admin/live-draw`. |
| SMS | — | No SMS provider integrated (phone numbers are collected at signup only). |

## Environment variables (names only)

**Frontend (Vite, public):** `VITE_SUPABASE_URL`, `VITE_SUPABASE_PUBLISHABLE_KEY` (aka anon key), `VITE_SUPABASE_PROJECT_ID`. Note: `scripts/generate-sitemap.ts` hardcodes the Supabase URL and anon key ([REDACTED] — publishable key, low risk, but should become an env var).

**Edge functions (secrets):**

| Name | Purpose |
|---|---|
| `SUPABASE_URL`, `SUPABASE_ANON_KEY`, `SUPABASE_PUBLISHABLE_KEY`, `SUPABASE_SERVICE_ROLE_KEY` | Platform-provided DB access |
| `CASHFLOWS_API_KEY`, `CASHFLOWS_CONFIGURATION_ID`, `CASHFLOWS_ENV`, `CASHFLOWS_BASE_URL` | Cashflows gateway |
| `SITE_BASE_URL`, `SITE_URL` | Return URLs / links in emails |
| `RESEND_API_KEY`, `FROM_EMAIL` | Email sending |
| `AUTO_DRAW_CRON_TOKEN`, `CRON_SECRET_KEY` | Shared secrets for pg_cron → edge function calls |
| `MAILCHIMP_API_KEY`, `MAILCHIMP_AUDIENCE_ID` | Legacy Mailchimp sync |
| `LOVABLE_API_KEY` / webhook signing secret | Used implicitly by `auth-email-hook` (Lovable platform; will not exist after migration) |

## Folder structure

```text
/                     index.html (meta, Pixel, Mailchimp, JSON-LD), vite/tailwind/ts configs
public/               favicon, og-image.png, robots.txt, sitemap.xml (generated), llms.txt, logos, images/
scripts/              generate-sitemap.ts
src/App.tsx           router (all routes) + providers
src/main.tsx          entry, font imports
src/contexts/         AuthContext (session, isAdmin), CartContext (basket in localStorage)
src/hooks/            data hooks (competitions, entries, instant win, player board, credits, winners)
src/lib/              business helpers: bundleDeals, bundleTiers, entryLimits, ticketNumbers, datetime, metaPixel, queryCache
src/pages/            public & account pages; src/pages/admin/ admin screens
src/components/       layout, home, competition, cart, instant-win (reveal games), admin, winner, account, ui (shadcn)
src/integrations/supabase/  generated client + DB types (auto-generated)
supabase/functions/   14 edge functions + _shared email templates
supabase/migrations/  123 SQL migration files (history; current truth = 03-schema.sql)
```

### File inventory

| File | Purpose |
|---|---|
| `scripts/entryLimits.test.ts` | Purpose not documented in-file; see name |
| `scripts/generate-sitemap.ts` | Build-time sitemap generator. Runs via predev/prebuild npm hooks; writes public/sitemap.xml. |
| `src/App.tsx` | Critical routes - eagerly loaded |
| `src/components/NavLink.tsx` | Component `NavLink` (see 07-components-and-design.md) |
| `src/components/ProtectedRoute.tsx` | Wait for both the session AND the role check before deciding — |
| `src/components/SEO.tsx` | Component `SEO` (see 07-components-and-design.md) |
| `src/components/ScrollToTop.tsx` | Component `ScrollToTop` (see 07-components-and-design.md) |
| `src/components/account/ResponsiblePlayPanel.tsx` | Component `ResponsiblePlayPanel` (see 07-components-and-design.md) |
| `src/components/admin/AdminLayout.tsx` | Component `AdminLayout` (see 07-components-and-design.md) |
| `src/components/admin/AdminPagination.tsx` | Component `AdminPagination` (see 07-components-and-design.md) |
| `src/components/admin/CompetitionRevenue.tsx` | Server-side aggregation: one row per competition, no paginated downloads |
| `src/components/admin/CompetitionSetsRevenue.tsx` | Build revenue map by competition_id |
| `src/components/admin/CreditHealthDashboard.tsx` | Component `CreditHealthDashboard` (see 07-components-and-design.md) |
| `src/components/admin/ImageUpload.tsx` | Compress/resize images client-side before upload: max width 1200px, |
| `src/components/admin/InstantWinPrizesEditor.tsx` | For large datasets: only fetch count from DB, don't load all into memory |
| `src/components/admin/PlayerBoardEditor.tsx` | Only allow replace if no spaces are sold |
| `src/components/admin/UserEntriesPanel.tsx` | Component `UserEntriesPanel` (see 07-components-and-design.md) |
| `src/components/admin/ViewAsUserDialog.tsx` | Enrich entries with competition titles |
| `src/components/admin/dashboard/AlertCard.tsx` | Component `AlertCard` (see 07-components-and-design.md) |
| `src/components/admin/dashboard/ComingSoonCard.tsx` | Component `ComingSoonCard` (see 07-components-and-design.md) |
| `src/components/admin/dashboard/CompetitionHealth.tsx` | Component `CompetitionHealth` (see 07-components-and-design.md) |
| `src/components/admin/draw/TicketRoller.tsx` | The winning ticket number the roller settles on. |
| `src/components/admin/draw/WinnerReveal.tsx` | Small caption under the card, e.g. the competition title. |
| `src/components/admin/finance/FinanceSubnav.tsx` | Component `FinanceSubnav` (see 07-components-and-design.md) |
| `src/components/admin/finance/SectionHeader.tsx` | Component `SectionHeader` (see 07-components-and-design.md) |
| `src/components/admin/operations/OperationsStats.tsx` | Component `OperationsStats` (see 07-components-and-design.md) |
| `src/components/admin/operations/OperationsSubnav.tsx` | Component `OperationsSubnav` (see 07-components-and-design.md) |
| `src/components/admin/ui/EmptyState.tsx` | Render without a wrapping Card (useful inside table cells). |
| `src/components/admin/ui/ErrorState.tsx` | Component `ErrorState` (see 07-components-and-design.md) |
| `src/components/admin/ui/KpiCard.tsx` | Standard admin KPI card. Visual style intentionally matches the |
| `src/components/admin/ui/LoadingState.tsx` | Component `LoadingState` (see 07-components-and-design.md) |
| `src/components/admin/ui/SectionHeader.tsx` | Shared admin section header. The finance subnav copy |
| `src/components/admin/ui/index.ts` | Component `index` (see 07-components-and-design.md) |
| `src/components/admin/users/UserActions.tsx` | Mobile layout stacks buttons full-width. |
| `src/components/admin/users/UserFilters.tsx` | Top-right toolbar for the users page: export menu + total count badge. |
| `src/components/admin/users/UserOverviewCards.tsx` | Component `UserOverviewCards` (see 07-components-and-design.md) |
| `src/components/admin/users/UserProfileHeader.tsx` | Component `UserProfileHeader` (see 07-components-and-design.md) |
| `src/components/admin/users/UserRow.tsx` | Component `UserRow` (see 07-components-and-design.md) |
| `src/components/admin/users/UserSearchBar.tsx` | Component `UserSearchBar` (see 07-components-and-design.md) |
| `src/components/admin/users/UserTable.tsx` | Component `UserTable` (see 07-components-and-design.md) |
| `src/components/admin/users/types.ts` | Component `types` (see 07-components-and-design.md) |
| `src/components/cart/CartSheet.tsx` | Get up to 3 upsell competitions not already in cart |
| `src/components/competition/CompetitionCard.tsx` | Component `CompetitionCard` (see 07-components-and-design.md) |
| `src/components/competition/CountdownTimer.tsx` | Component `CountdownTimer` (see 07-components-and-design.md) |
| `src/components/competition/EntryPurchaseDialog.tsx` | Free entry - create directly |
| `src/components/competition/FreePostalEntryButton.tsx` | Component `FreePostalEntryButton` (see 07-components-and-design.md) |
| `src/components/competition/InstantWinReveal.tsx` | Treat "No Prize" as a loss |
| `src/components/competition/PlayerBoard.tsx` | Component `PlayerBoard` (see 07-components-and-design.md) |
| `src/components/home/BrandsStrip.tsx` | Component `BrandsStrip` (see 07-components-and-design.md) |
| `src/components/home/EmailSignup.tsx` | Component `EmailSignup` (see 07-components-and-design.md) |
| `src/components/home/FeaturedCompetitions.tsx` | Component `FeaturedCompetitions` (see 07-components-and-design.md) |
| `src/components/home/HeroSection.tsx` | Component `HeroSection` (see 07-components-and-design.md) |
| `src/components/home/HowItWorksSection.tsx` | Component `HowItWorksSection` (see 07-components-and-design.md) |
| `src/components/home/JoinCommunity.tsx` | Component `JoinCommunity` (see 07-components-and-design.md) |
| `src/components/home/LiveActivityToast.tsx` | Previously this component ran a full `entries` scan on every homepage load |
| `src/components/home/PlatformStats.tsx` | Component `PlatformStats` (see 07-components-and-design.md) |
| `src/components/home/WhyVaultNine.tsx` | Component `WhyVaultNine` (see 07-components-and-design.md) |
| `src/components/home/WinnersPreview.tsx` | Component `WinnersPreview` (see 07-components-and-design.md) |
| `src/components/instant-win/InstantWinReveal.tsx` | Registry id. Missing or unknown ids use "classic". |
| `src/components/instant-win/adapt.ts` | Turns the site's existing instant-win result into the shape the reveal games |
| `src/components/instant-win/arcade.ts` | Chunky arcade surfaces, shared by every reveal game so the machines look like |
| `src/components/instant-win/brand.tsx` | Vault Nine brand furniture for the reveal games. |
| `src/components/instant-win/games/DartboardReveal.tsx` | Dartboard reveal, one throw per ticket. |
| `src/components/instant-win/games/WheelReveal.tsx` | Spin the wheel, one spin per ticket. Presentation only: the segment the wheel |
| `src/components/instant-win/registry.ts` | Every reveal game, keyed by id. To add a game: create a component that takes |
| `src/components/instant-win/reveal-flow.ts` | The state machine every reveal game shares: play the animation, stagger the wins |
| `src/components/instant-win/reveal-parts.tsx` | Shared presentation for every reveal game: the card shell, the win and no-win |
| `src/components/instant-win/reveal-result.tsx` | The finished result, identical across every game: the headline, the winning |
| `src/components/instant-win/reveal-sound.ts` | Sound for the reveal games, synthesised with the Web Audio API so there are no |
| `src/components/instant-win/reveal-utils.ts` | Above this many tickets, reveals summarise non-winning tickets instead of rendering each one. |
| `src/components/instant-win/types.ts` | Instant win reveal contract. |
| `src/components/instant-win/venue.tsx` | The room the board hangs in: the Vault Nine stage artwork, which already has |
| `src/components/layout/AnnouncementBar.tsx` | Component `AnnouncementBar` (see 07-components-and-design.md) |
| `src/components/layout/Footer.tsx` | Component `Footer` (see 07-components-and-design.md) |
| `src/components/layout/Layout.tsx` | Component `Layout` (see 07-components-and-design.md) |
| `src/components/layout/MobileStickyCTA.tsx` | Get the hottest active competition (featured or soonest draw date) |
| `src/components/layout/Navigation.tsx` | Component `Navigation` (see 07-components-and-design.md) |
| `src/components/trustpilot/TrustpilotCollectWidget.tsx` | Trustpilot "Collect Reviews" TrustBox - prompts visitors to leave a review. |
| `src/components/winner/WinnerVerification.tsx` | Use a SECURITY DEFINER RPC scoped to auth.uid() so the user can read |
| `src/contexts/AuthContext.tsx` | Only re-check admin role when the actual user identity changes |
| `src/contexts/CartContext.tsx` | Fixed-price bundle tier |
| `src/data/competitions.ts` | Purpose not documented in-file; see name |
| `src/hooks/use-mobile.tsx` | React hook `use-mobile` (data fetching / state) |
| `src/hooks/use-toast.ts` | React hook `use-toast` (data fetching / state) |
| `src/hooks/useAnnouncementBar.ts` | React hook `useAnnouncementBar` (data fetching / state) |
| `src/hooks/useCompetitions.ts` | Explicitly select columns, excluding quiz_answer for security |
| `src/hooks/useEntryPurchase.ts` | Paid entries are created server-side by the payment webhook only. |
| `src/hooks/useInstantWin.ts` | React hook `useInstantWin` (data fetching / state) |
| `src/hooks/usePlayerBoard.ts` | Lightweight: refresh only the "which spaces are mine" map (single RPC), |
| `src/hooks/useSiteCredits.ts` | React hook `useSiteCredits` (data fetching / state) |
| `src/hooks/useUserEntries.ts` | Hook to fetch user's entries for a single competition |
| `src/hooks/useWinners.ts` | Only select non-sensitive columns; full `*` is blocked for anon by |
| `src/integrations/supabase/client.ts` | This file is automatically generated. Do not edit it directly. |
| `src/integrations/supabase/previewAuthStorage.ts` | This file is automatically generated. Do not edit it directly. |
| `src/integrations/supabase/types.ts` | Allows to automatically instantiate createClient with right options |
| `src/lib/bundleDeals.ts` | Calculate bonus tickets for a "Buy X Get Y Free" bundle deal. |
| `src/lib/bundleTiers.ts` | Purpose not documented in-file; see name |
| `src/lib/competitionImages.ts` | Generated competition images mapping |
| `src/lib/datetime.ts` | Shared date/timezone utilities for Vault Nine admin. |
| `src/lib/entryLimits.ts` | Purpose not documented in-file; see name |
| `src/lib/metaPixel.ts` | Meta Pixel helper – thin wrapper around fbq() |
| `src/lib/queryCache.ts` | Lightweight in-memory TTL cache for expensive read-only queries that would |
| `src/lib/ticketNumbers.ts` | Competitions using random ticket allocation store every issued ticket number |
| `src/lib/utils.ts` | Purpose not documented in-file; see name |
| `src/main.tsx` | Brand fonts |
| `src/pages/About.tsx` | Public/account page: About (see 02-routes-and-pages.md) |
| `src/pages/AcceptableUse.tsx` | Public/account page: Acceptable Use (see 02-routes-and-pages.md) |
| `src/pages/Account.tsx` | Public/account page: Account (see 02-routes-and-pages.md) |
| `src/pages/AuditReport.tsx` | Ticket checker state |
| `src/pages/Auth.tsx` | Public/account page: Auth (see 02-routes-and-pages.md) |
| `src/pages/CashflowsDocs.tsx` | Public/account page: Cashflows Docs (see 02-routes-and-pages.md) |
| `src/pages/CashflowsPaymentCancelled.tsx` | Public/account page: Cashflows Payment Cancelled (see 02-routes-and-pages.md) |
| `src/pages/CashflowsPaymentFailed.tsx` | Public/account page: Cashflows Payment Failed (see 02-routes-and-pages.md) |
| `src/pages/CashflowsPaymentSuccess.tsx` | Public/account page: Cashflows Payment Success (see 02-routes-and-pages.md) |
| `src/pages/CompetitionDetail.tsx` | Redirect UUID URLs to clean slug URL once competition resolves |
| `src/pages/Competitions.tsx` | Sold-out competitions always go to the bottom |
| `src/pages/Contact.tsx` | Send email via edge function |
| `src/pages/Cookies.tsx` | Public/account page: Cookies (see 02-routes-and-pages.md) |
| `src/pages/DrawResults.tsx` | Public/account page: Draw Results (see 02-routes-and-pages.md) |
| `src/pages/FAQ.tsx` | Recursively extract plain text from a React node tree for JSON-LD output |
| `src/pages/HowItWorks.tsx` | Public/account page: How It Works (see 02-routes-and-pages.md) |
| `src/pages/Index.tsx` | Public/account page: Index (see 02-routes-and-pages.md) |
| `src/pages/NotFound.tsx` | Public/account page: Not Found (see 02-routes-and-pages.md) |
| `src/pages/PostalEntry.tsx` | Public/account page: Postal Entry (see 02-routes-and-pages.md) |
| `src/pages/Privacy.tsx` | Public/account page: Privacy (see 02-routes-and-pages.md) |
| `src/pages/ResetPassword.tsx` | Listen for auth state changes FIRST (before getSession) |
| `src/pages/ResponsiblePlay.tsx` | Public/account page: Responsible Play (see 02-routes-and-pages.md) |
| `src/pages/ScratchTest.tsx` | Public/account page: Scratch Test (see 02-routes-and-pages.md) |
| `src/pages/Terms.tsx` | Public/account page: Terms (see 02-routes-and-pages.md) |
| `src/pages/TermsOfUse.tsx` | Public/account page: Terms Of Use (see 02-routes-and-pages.md) |
| `src/pages/Winners.tsx` | Public/account page: Winners (see 02-routes-and-pages.md) |
| `src/pages/admin/AdminAnnouncementBar.tsx` | Admin screen: Announcement Bar (see 06-admin.md) |
| `src/pages/admin/AdminAuditTrails.tsx` | Admin screen: Audit Trails (see 06-admin.md) |
| `src/pages/admin/AdminBulkDailies.tsx` | Admin screen: Bulk Dailies (see 06-admin.md) |
| `src/pages/admin/AdminCompetitionForm.tsx` | Re-export to avoid touching downstream references; underlying helpers |
| `src/pages/admin/AdminCompetitions.tsx` | Admin screen: Competitions (see 06-admin.md) |
| `src/pages/admin/AdminDashboard.tsx` | KPIs (range-dependent) |
| `src/pages/admin/AdminDataExport.tsx` | Admin screen: Data Export (see 06-admin.md) |
| `src/pages/admin/AdminEmailCampaigns.tsx` | Admin screen: Email Campaigns (see 06-admin.md) |
| `src/pages/admin/AdminEntries.tsx` | Stats (aggregated separately) |
| `src/pages/admin/AdminInfluencers.tsx` | Calculate stats from orders |
| `src/pages/admin/AdminInstantWinPreview.tsx` | Preview for the instant-win reveal games. Admin only, and it talks to nothing: |
| `src/pages/admin/AdminInstantWinTest.tsx` | Admin screen: Instant Win Test (see 06-admin.md) |
| `src/pages/admin/AdminInstantWins.tsx` | When filtering winners only, we need to fetch all pages because most |
| `src/pages/admin/AdminLiveDraw.tsx` | Admin screen: Live Draw (see 06-admin.md) |
| `src/pages/admin/AdminLiveDrawTest.tsx` | Admin screen: Live Draw Test (see 06-admin.md) |
| `src/pages/admin/AdminPostalEntries.tsx` | Admin screen: Postal Entries (see 06-admin.md) |
| `src/pages/admin/AdminPromoCodes.tsx` | Admin screen: Promo Codes (see 06-admin.md) |
| `src/pages/admin/AdminReports.tsx` | Admin screen: Reports (see 06-admin.md) |
| `src/pages/admin/AdminSiteCredits.tsx` | Fetch all credit transactions |
| `src/pages/admin/AdminTransactions.tsx` | Fetch competitions once |
| `src/pages/admin/AdminUserProfile.tsx` | Admin screen: User Profile (see 06-admin.md) |
| `src/pages/admin/AdminUsers.tsx` | Admin screen: Users (see 06-admin.md) |
| `src/pages/admin/AdminWebhookLogs.tsx` | Admin screen: Webhook Logs (see 06-admin.md) |
| `src/pages/admin/AdminWinnerVerification.tsx` | Admin screen: Winner Verification (see 06-admin.md) |
| `src/pages/admin/AdminWinners.tsx` | Admin screen: Winners (see 06-admin.md) |
| `src/vite-env.d.ts` | Purpose not documented in-file; see name |
| `supabase/functions/auth-email-hook/index.ts` | Template mapping |
| `supabase/functions/auto-draw-scheduled/index.ts` | Cryptographically secure random integer in [0, max) |
| `supabase/functions/cashflows-webhook/index.ts` | Log webhook (capture id so we can update THIS row later, not every cashflows row) |
| `supabase/functions/create-cashflows-checkout/index.ts` | Helper: wrap a promise with a timeout |
| `supabase/functions/delete-user/index.ts` | Verify the caller is an admin |
| `supabase/functions/draw-winner/index.ts` | Check competition |
| `supabase/functions/expire-stuck-orders/index.ts` | Scheduled function: cancels orders stuck in pending_payment > 2h |
| `supabase/functions/mailchimp-sync/index.ts` | Mailchimp sync edge function |
| `supabase/functions/order-status/index.ts` | Require a valid signed-in user |
| `supabase/functions/player-board-checkout/index.ts` | Edge function (see 04-edge-functions.md) |
| `supabase/functions/publish-scheduled-competitions/index.ts` | Auth: only CRON_SECRET_KEY or service role can publish scheduled competitions. |
| `supabase/functions/send-bulk-email/index.ts` | Input validation constants |
| `supabase/functions/send-draw-reminders/index.ts` | Verify authorization - only CRON_SECRET_KEY or SUPABASE_SERVICE_ROLE_KEY. |
| `supabase/functions/send-email/index.ts` | Escape HTML special chars so untrusted input (e.g. contact form fields) |
