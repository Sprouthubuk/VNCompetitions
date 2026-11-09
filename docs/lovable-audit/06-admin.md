# 06 — Admin

## How admin roles work

- Roles live in `public.user_roles` (`app_role` enum: `admin`, `user`). Every signup gets `user` via `handle_new_user()`.
- Admin check: `public.has_role(auth.uid(), 'admin')` used by RLS policies, security-definer RPCs (`admin_*`) and edge functions (`draw-winner`, `auto-draw-scheduled`, `mailchimp-sync`, `send-bulk-email`, `delete-user`).
- Frontend: `AuthContext` exposes `isAdmin`; `ProtectedRoute requireAdmin` guards `/admin/*` (UX only).
- **Granting admin:** no UI for promoting users was confirmed — done by inserting `(user_id,'admin')` into `user_roles` via SQL. At export time only one account held `admin` (company inbox). `AdminUsers.tsx` reads `user_roles` (Unclear whether it can edit them).
- Layout: `src/components/admin/AdminLayout.tsx` (sidebar nav).

## Screens

| Screen | File | Capabilities |
|---|---|---|
| Dashboard | `AdminDashboard.tsx`, `components/admin/dashboard/*` | Today/30-day/all-time revenue, orders, recent winners/entries, charts (Recharts) |
| Competitions list | `AdminCompetitions.tsx` | Search/filter, status, duplicate, delete, Paid/Unpaid "prize purchased" toggle (optimistic + rollback), revenue per comp |
| Competition form | `AdminCompetitionForm.tsx`, `ImageUpload.tsx`, `InstantWinPrizesEditor.tsx`, `PlayerBoardEditor.tsx` | All fields (see 05 §1), UK-time scheduling (UTC conversion), image upload (resized to 1200px JPEG 80%), instant-win game picker, random allocation toggle (disabled for instant win), bundle tiers, multipliers, cash alternative, quiz, draws-on-sellout, prize spreadsheet import (CSV/XLSX), player board spaces |
| Bulk dailies | `AdminBulkDailies.tsx` | Create many scheduled daily comps at once |
| Users / user profile | `AdminUsers.tsx`, `AdminUserProfile.tsx`, `components/admin/users/*`, `UserEntriesPanel.tsx`, `ViewAsUserDialog.tsx` | Search users, entries, orders, credit balance, wins, "view as user" |
| Entries | `AdminEntries.tsx` | Browse entries per competition with ticket numbers |
| Postal entries | `AdminPostalEntries.tsx` | Log one envelope per row (name, email, address) |
| Live draw | `AdminLiveDraw.tsx`, `components/admin/draw/TicketRoller.tsx`, `WinnerReveal.tsx` | Select comp, ticket mapping, crypto-random pick via `draw-winner`, Presentation Mode for Facebook Live, record winner + audit |
| Live draw test | `AdminLiveDrawTest.tsx` | Fake data rehearsal, no writes/emails |
| Instant-win test / preview | `AdminInstantWinTest.tsx`, `AdminInstantWinPreview.tsx` | Fake multi-ticket reveal timing; Dartboard/Wheel game preview |
| Instant wins | `AdminInstantWins.tsx` | Results, physical prize tab (fulfilment status), winners-only batched pagination |
| Winners | `AdminWinners.tsx` | Announce, winner photos, prize descriptions |
| Winner verification | `AdminWinnerVerification.tsx`, `components/winner/WinnerVerification.tsx` | View ID via signed URL, approve/reject (sends Identity Verified marketing email), dispatch |
| Audit trails | `AdminAuditTrails.tsx` | Draw audits, publish to `/audit/:id`, video timestamp |
| Webhook logs | `AdminWebhookLogs.tsx` | Cashflows payloads and processing results |
| Transactions | `AdminTransactions.tsx` | Orders with status, Cashflows refs, promo |
| Promo codes | `AdminPromoCodes.tsx` | CRUD, exclusions, usage |
| Influencers | `AdminInfluencers.tsx` | Influencers, commission rate/type, linked promo, totals |
| Site credits | `AdminSiteCredits.tsx`, `CreditHealthDashboard.tsx` | Grant/deduct credit, outstanding liability (`admin_credit_health`) |
| Reports | `AdminReports.tsx`, `CompetitionRevenue.tsx`, `CompetitionSetsRevenue.tsx`, `components/admin/finance/*` | Per-competition cash revenue, credit used, prize costs, estimated Cashflows fee (`estimate_payment_fee`: 1.9% + 20p), profit; CSV + PDF export; `admin_competition_report` cached 5 min |
| Email campaigns | `AdminEmailCampaigns.tsx` | Compose and send bulk email (`send-bulk-email`), Mailchimp sync |
| Announcement bar | `AdminAnnouncementBar.tsx` | Toggle, message, promo code, link |
| Data export | `AdminDataExport.tsx` | Temporary: CSV export of 14 tables (uses `admin_*` RPCs where RLS blocks) |
| Operations | `components/admin/operations/*` | Operational widgets (Unclear exact scope — see files) |
