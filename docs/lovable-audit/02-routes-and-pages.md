# 02 — Routes & Pages

All routes are declared in `src/App.tsx` (React Router v6, client-side). Wrapped in `Layout` (Navigation, AnnouncementBar, Footer, MobileStickyCTA) unless noted. Auth guard: `src/components/ProtectedRoute.tsx` — redirects to `/auth` if no session; with `requireAdmin` it also checks `isAdmin` from `AuthContext` (which calls `has_role(uid,'admin')`). **The guard is UX only; real protection is RLS + security-definer RPC checks.**

Legend — Auth: **P** public, **U** signed-in user, **A** admin.

## Public & account routes

| Route | Auth | Page file | What it shows | Data read / written | Main components |
|---|---|---|---|---|---|
| `/` | P | `Index.tsx` | Homepage: hero, featured/live competitions, winners carousel, how it works, platform stats, email signup | Reads `competitions_public` (via `useCompetitions`), `winners` (announced), RPC `get_platform_stats` | HeroSection, FeaturedCompetitions, WinnersPreview, HowItWorksSection, PlatformStats, WhyVaultNine, BrandsStrip, JoinCommunity, EmailSignup, LiveActivityToast |
| `/competitions` | P | `Competitions.tsx` | Grid of live competitions (2-col mobile), filters, sold-out at bottom; pinned priority comps | `competitions_public` | CompetitionCard |
| `/competition/:id` | P (buy needs U) | `CompetitionDetail.tsx` | Detail page; `:id` = slug or UUID (UUID redirects to slug). Price/sale price, bundle tiers, multipliers, cash alternative, countdown or "draws when sold out", quantity slider + chips (default 5), skill question, add-to-basket, Free Postal Entry button, instant-win prize table ("Vault Cards") | `competitions_public`, RPCs `get_instant_win_prize_status`, `validate_quiz_answer`, `get_my_competition_entry_count`; writes basket (localStorage via CartContext). Free comps call RPC `enter_free_competition` | CountdownTimer, EntryPurchaseDialog, FreePostalEntryButton, PlayerBoard (player_board type), InstantWinReveal |
| `/how-it-works` | P | `HowItWorks.tsx` | Static explainer | — | — |
| `/winners` | P | `Winners.tsx` | Announced winners (photos / trophy fallback) | `winners` (announced = true), competitions | — |
| `/draw-results` | P | `DrawResults.tsx` | Draw results with winning ticket number | RPC `get_announced_draw_results`, competitions | — |
| `/audit/:id` | P | `AuditReport.tsx` | Public audit certificate for a draw; ticket verification | `draw_audits` (published), RPC `verify_ticket_in_draw` | — |
| `/postal-entry`, `/free-entry` | P | `PostalEntry.tsx` | Free postal entry instructions (address, one entry per envelope, no per-person cap) | Static | — |
| `/about`, `/contact`, `/faq`, `/terms`, `/terms-of-use`, `/acceptable-use`, `/privacy`, `/cookies`, `/responsible-play` | P | same-named pages | Static content. Contact form posts to `send-email` (type `contact`, fixed inbox) | — | — |
| `/auth` | P | `Auth.tsx` | Sign in / sign up (first, last name, phone, address, postcode; zod validated) | Supabase Auth `signUp` / `signInWithPassword`; profile created by DB trigger | — |
| `/reset-password` | P | `ResetPassword.tsx` | Password reset from email link (hash token extraction, anti-scanner) | Supabase Auth `updateUser` | — |
| `/account` | U | `Account.tsx` | Tabs: entries & ticket numbers, instant-win results, My Wins (physical prizes pinned), site credit balance, ID verification upload, profile, marketing opt-in, responsible play limits, delete account | `entries`, `entry_tickets`, `instant_win_results`, `orders`, `profiles`, `site_credits`, `user_limits`; RPCs `get_user_credit_balance`, `get_user_won_prize_tickets`, `get_my_winner_records`, `upload_winner_id_document`, `get_user_spend_summary`; Storage upload to `winner-id-documents`; edge functions `delete-user`, `mailchimp-sync` | ResponsiblePlayPanel, WinnerVerification |
| `/payment/success` | P (polling needs U token) | `CashflowsPaymentSuccess.tsx` | Polls `order-status` every 2s (max 15), then sequential instant-win reveals and summary; Meta Purchase event; Trustpilot widget | Edge `order-status` | InstantWinReveal (games registry), TrustpilotCollectWidget |
| `/payment/failed`, `/payment/cancelled` | P | `CashflowsPaymentFailed.tsx`, `CashflowsPaymentCancelled.tsx` | Failure/cancel messaging | — | — |
| `/docs/payments/cashflows` | P | `CashflowsDocs.tsx` | Internal integration notes page (publicly reachable; disallowed in robots) | — | — |
| `/scratch-test` | P (hidden) | `ScratchTest.tsx` | Scratch card prototype, no real prizes | — | — |
| Redirects | P | — | `/faqs`→`/faq`, `/cart`→`/competitions`, `/home`→`/`, `/login|/signup|/register`→`/auth`, `/instant-win-preview`→`/admin/instant-win-preview` | — | — |
| `*` | P | `NotFound.tsx` | 404 | — | — |

The basket is not a route: it is the `CartSheet` drawer (`src/components/cart/CartSheet.tsx`), rendered once in Navigation; it calls `create-cashflows-checkout` (and `player-board-checkout` for player-board items).

## Admin routes (all `ProtectedRoute requireAdmin`)

| Route | Page | Purpose (details in 06-admin.md) |
|---|---|---|
| `/admin`, `/admin/dashboard` | AdminDashboard | KPIs, revenue, recent activity |
| `/admin/competitions` | AdminCompetitions | List, duplicate, status, prize purchased toggle |
| `/admin/competitions/new`, `/admin/competitions/:id` | AdminCompetitionForm | Create / edit competition |
| `/admin/competitions/bulk-dailies` | AdminBulkDailies | Bulk-create daily competitions |
| `/admin/users`, `/admin/users/:id` | AdminUsers, AdminUserProfile | Users, roles, per-user entries/credits/wins |
| `/admin/entries` | AdminEntries | Entries browser |
| `/admin/winners` | AdminWinners | Winners management, announce, photos |
| `/admin/winner-verification` | AdminWinnerVerification | ID verification review (signed URLs) |
| `/admin/live-draw` | AdminLiveDraw | Live draw + Presentation Mode (calls `draw-winner`) |
| `/admin/live-draw-test` | AdminLiveDrawTest | Fake-data draw rehearsal |
| `/admin/instant-win-test`, `/admin/instant-win-preview` | AdminInstantWinTest, AdminInstantWinPreview | Fake-data reveal/game previews |
| `/admin/instant-wins` | AdminInstantWins | Instant-win results & physical prize fulfilment |
| `/admin/postal-entries` | AdminPostalEntries | Log postal envelopes |
| `/admin/audit-trails` | AdminAuditTrails | Draw audits, publish |
| `/admin/webhook-logs` | AdminWebhookLogs | Cashflows webhook log viewer |
| `/admin/promo-codes` | AdminPromoCodes | Promo codes CRUD |
| `/admin/influencers` | AdminInfluencers | Influencers & commissions |
| `/admin/site-credits` | AdminSiteCredits | Grant/deduct credit, credit health |
| `/admin/transactions` | AdminTransactions | Orders |
| `/admin/emails` | AdminEmailCampaigns | Bulk email campaigns |
| `/admin/announcement` | AdminAnnouncementBar | Site announcement bar toggle/message |
| `/admin/reports` | AdminReports | Financial report per competition, CSV/PDF |
| `/admin/data-export` | AdminDataExport | Temporary full-table CSV export |
