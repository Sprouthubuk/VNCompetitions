# 04 — Edge Functions

Runtime: Supabase Edge Functions (Deno). Source in `supabase/functions/<name>/index.ts`. `verify_jwt` flags come from `supabase/config.toml`. Any hardcoded secret values are replaced with `[REDACTED]` (none were found in function source; secrets are read from `Deno.env`).

## Summary

| Function | verify_jwt | Env vars read |
|---|---|---|
| auth-email-hook | false | LOVABLE_API_KEY |
| auto-draw-scheduled | false | AUTO_DRAW_CRON_TOKEN, CRON_SECRET_KEY, SUPABASE_ANON_KEY, SUPABASE_PUBLISHABLE_KEY, SUPABASE_SERVICE_ROLE_KEY, SUPABASE_URL |
| cashflows-webhook | false | CASHFLOWS_API_KEY, CASHFLOWS_BASE_URL, CASHFLOWS_CONFIGURATION_ID, CASHFLOWS_ENV, SITE_BASE_URL, SUPABASE_SERVICE_ROLE_KEY, SUPABASE_URL |
| create-cashflows-checkout | false | CASHFLOWS_API_KEY, CASHFLOWS_BASE_URL, CASHFLOWS_CONFIGURATION_ID, CASHFLOWS_ENV, SITE_BASE_URL, SUPABASE_ANON_KEY, SUPABASE_SERVICE_ROLE_KEY, SUPABASE_URL |
| delete-user | default (true) | SUPABASE_ANON_KEY, SUPABASE_SERVICE_ROLE_KEY, SUPABASE_URL |
| draw-winner | false | SUPABASE_ANON_KEY, SUPABASE_SERVICE_ROLE_KEY, SUPABASE_URL |
| expire-stuck-orders | false | AUTO_DRAW_CRON_TOKEN, CRON_SECRET_KEY, SUPABASE_SERVICE_ROLE_KEY, SUPABASE_URL |
| mailchimp-sync | default (true) | MAILCHIMP_API_KEY, MAILCHIMP_AUDIENCE_ID, SUPABASE_ANON_KEY, SUPABASE_SERVICE_ROLE_KEY, SUPABASE_URL |
| order-status | false | SUPABASE_ANON_KEY, SUPABASE_PUBLISHABLE_KEY, SUPABASE_SERVICE_ROLE_KEY, SUPABASE_URL |
| player-board-checkout | false | CASHFLOWS_API_KEY, CASHFLOWS_BASE_URL, CASHFLOWS_CONFIGURATION_ID, CASHFLOWS_ENV, SITE_BASE_URL, SUPABASE_ANON_KEY, SUPABASE_SERVICE_ROLE_KEY, SUPABASE_URL |
| publish-scheduled-competitions | default (true) | CRON_SECRET_KEY, SUPABASE_SERVICE_ROLE_KEY, SUPABASE_URL |
| send-bulk-email | true | FROM_EMAIL, RESEND_API_KEY, SITE_URL, SUPABASE_SERVICE_ROLE_KEY, SUPABASE_URL |
| send-draw-reminders | false | CRON_SECRET_KEY, SITE_URL, SUPABASE_SERVICE_ROLE_KEY, SUPABASE_URL |
| send-email | false | FROM_EMAIL, RESEND_API_KEY, SUPABASE_ANON_KEY, SUPABASE_SERVICE_ROLE_KEY, SUPABASE_URL |

---

## auth-email-hook

- **Purpose / logic:** Supabase Auth "Send Email" hook. Renders branded React-email templates (_shared/email-templates: signup, recovery, magic-link, invite, email-change, reauthentication) and sends them.
- **Trigger & auth:** Called by Supabase Auth (HTTP hook). verify_jwt=false; verifies webhook signature + timestamp headers (x-lovable-signature / x-lovable-timestamp) via @lovable.dev/webhooks-js.
- **Inputs:** Auth hook payload (user, email_data: token, token_hash, redirect_to, email_action_type).
- **Outputs / external calls:** Sends via Lovable email sending API; returns 200 / 401 invalid signature.

### `supabase/functions/auth-email-hook/deno.json`

````json
{
  "compilerOptions": {
    "jsx": "react-jsx",
    "jsxImportSource": "npm:react@18.3.1",
    "types": ["npm:@types/react@18.3.1"]
  }
}

````

### `supabase/functions/auth-email-hook/index.ts`

````ts
import * as React from 'npm:react@18.3.1'
import { renderAsync } from 'npm:@react-email/components@0.0.22'
import { sendLovableEmail, parseEmailWebhookPayload } from 'npm:@lovable.dev/email-js'
import { WebhookError, verifyWebhookRequest } from 'npm:@lovable.dev/webhooks-js'
import { SignupEmail } from '../_shared/email-templates/signup.tsx'
import { InviteEmail } from '../_shared/email-templates/invite.tsx'
import { MagicLinkEmail } from '../_shared/email-templates/magic-link.tsx'
import { RecoveryEmail } from '../_shared/email-templates/recovery.tsx'
import { EmailChangeEmail } from '../_shared/email-templates/email-change.tsx'
import { ReauthenticationEmail } from '../_shared/email-templates/reauthentication.tsx'

const corsHeaders = {
  'Access-Control-Allow-Origin': '*',
  'Access-Control-Allow-Headers':
    'authorization, x-client-info, apikey, content-type, x-lovable-signature, x-lovable-timestamp, x-supabase-client-platform, x-supabase-client-platform-version, x-supabase-client-runtime, x-supabase-client-runtime-version',
}

const EMAIL_SUBJECTS: Record<string, string> = {
  signup: 'Confirm your email',
  invite: "You've been invited",
  magiclink: 'Your login link',
  recovery: 'Reset your password',
  email_change: 'Confirm your new email',
  reauthentication: 'Your verification code',
}

// Template mapping
const EMAIL_TEMPLATES: Record<string, React.ComponentType<any>> = {
  signup: SignupEmail,
  invite: InviteEmail,
  magiclink: MagicLinkEmail,
  recovery: RecoveryEmail,
  email_change: EmailChangeEmail,
  reauthentication: ReauthenticationEmail,
}

// Configuration
const SITE_NAME = "vaultninecompetitions"
const SENDER_DOMAIN = "notify.vaultninecompetitions.co.uk"
const ROOT_DOMAIN = "vaultninecompetitions.co.uk"
const FROM_DOMAIN = "vaultninecompetitions.co.uk" // Domain shown in From address (may be root or sender subdomain)

// Sample data for preview mode ONLY (not used in actual email sending).
// URLs are baked in at scaffold time from the project's real data.
// The sample email uses a fixed placeholder (RFC 6761 .test TLD) so the Go backend
// can always find-and-replace it with the actual recipient when sending test emails,
// even if the project's domain has changed since the template was scaffolded.
const SAMPLE_PROJECT_URL = "https://vaultninecompetitions.lovable.app"
const SAMPLE_EMAIL = "user@example.test"
const SAMPLE_DATA: Record<string, object> = {
  signup: {
    siteName: SITE_NAME,
    siteUrl: SAMPLE_PROJECT_URL,
    recipient: SAMPLE_EMAIL,
    confirmationUrl: SAMPLE_PROJECT_URL,
  },
  magiclink: {
    siteName: SITE_NAME,
    confirmationUrl: SAMPLE_PROJECT_URL,
  },
  recovery: {
    siteName: SITE_NAME,
    confirmationUrl: SAMPLE_PROJECT_URL,
  },
  invite: {
    siteName: SITE_NAME,
    siteUrl: SAMPLE_PROJECT_URL,
    confirmationUrl: SAMPLE_PROJECT_URL,
  },
  email_change: {
    siteName: SITE_NAME,
    email: SAMPLE_EMAIL,
    newEmail: SAMPLE_EMAIL,
    confirmationUrl: SAMPLE_PROJECT_URL,
  },
  reauthentication: {
    token: '123456',
  },
}

// Preview endpoint handler - returns rendered HTML without sending email
async function handlePreview(req: Request): Promise<Response> {
  const previewCorsHeaders = {
    'Access-Control-Allow-Origin': '*',
    'Access-Control-Allow-Headers': 'authorization, content-type',
  }

  if (req.method === 'OPTIONS') {
    return new Response(null, { headers: previewCorsHeaders })
  }

  const apiKey = Deno.env.get('LOVABLE_API_KEY')
  const authHeader = req.headers.get('Authorization')

  if (!apiKey || authHeader !== `Bearer ${apiKey}`) {
    return new Response(JSON.stringify({ error: 'Unauthorized' }), {
      status: 401,
      headers: { ...previewCorsHeaders, 'Content-Type': 'application/json' },
    })
  }

  let type: string
  try {
    const body = await req.json()
    type = body.type
  } catch (error) {
    return new Response(JSON.stringify({ error: 'Invalid JSON in request body' }), {
      status: 400,
      headers: { ...previewCorsHeaders, 'Content-Type': 'application/json' },
    })
  }

  const EmailTemplate = EMAIL_TEMPLATES[type]

  if (!EmailTemplate) {
    return new Response(JSON.stringify({ error: `Unknown email type: ${type}` }), {
      status: 400,
      headers: { ...previewCorsHeaders, 'Content-Type': 'application/json' },
    })
  }

  const sampleData = SAMPLE_DATA[type] || {}
  const html = await renderAsync(React.createElement(EmailTemplate, sampleData))

  return new Response(html, {
    status: 200,
    headers: { ...previewCorsHeaders, 'Content-Type': 'text/html; charset=utf-8' },
  })
}

// Webhook handler - verifies signature and sends email
async function handleWebhook(req: Request): Promise<Response> {
  const apiKey = Deno.env.get('LOVABLE_API_KEY')

  if (!apiKey) {
    console.error('LOVABLE_API_KEY not configured')
    return new Response(
      JSON.stringify({ error: 'Server configuration error' }),
      { status: 500, headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
    )
  }

  // Verify signature + timestamp, then parse payload.
  let payload: any
  let run_id = ''
  try {
    const verified = await verifyWebhookRequest({
      req,
      secret: apiKey,
      parser: parseEmailWebhookPayload,
    })
    payload = verified.payload
    run_id = payload.run_id
  } catch (error) {
    if (error instanceof WebhookError) {
      switch (error.code) {
        case 'invalid_signature':
        case 'missing_timestamp':
        case 'invalid_timestamp':
        case 'stale_timestamp':
          console.error('Invalid webhook signature', { error: error.message })
          return new Response(JSON.stringify({ error: 'Invalid signature' }), {
            status: 401,
            headers: { ...corsHeaders, 'Content-Type': 'application/json' },
          })
        case 'invalid_payload':
        case 'invalid_json':
          console.error('Invalid webhook payload', { error: error.message })
          return new Response(
            JSON.stringify({ error: 'Invalid webhook payload' }),
            { status: 400, headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
          )
      }
    }

    console.error('Webhook verification failed', { error })
    return new Response(
      JSON.stringify({ error: 'Invalid webhook payload' }),
      { status: 400, headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
    )
  }

  if (!run_id) {
    console.error('Webhook payload missing run_id')
    return new Response(
      JSON.stringify({ error: 'Invalid webhook payload' }),
      {
        status: 400,
        headers: { ...corsHeaders, 'Content-Type': 'application/json' },
      }
    )
  }

  if (payload.version !== '1') {
    console.error('Unsupported payload version', { version: payload.version, run_id })
    return new Response(
      JSON.stringify({ error: `Unsupported payload version: ${payload.version}` }),
      {
        status: 400,
        headers: { ...corsHeaders, 'Content-Type': 'application/json' },
      }
    )
  }

  // The email action type is in payload.data.action_type (e.g., "signup", "recovery")
  // payload.type is the hook event type ("auth")
  const emailType = payload.data.action_type
  console.log('Received auth event', { emailType, email: payload.data.email, run_id })

  const EmailTemplate = EMAIL_TEMPLATES[emailType]
  if (!EmailTemplate) {
    console.error('Unknown email type', { emailType, run_id })
    return new Response(
      JSON.stringify({ error: `Unknown email type: ${emailType}` }),
      { status: 400, headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
    )
  }

  // Build template props from payload.data (HookData structure)
  const templateProps = {
    siteName: SITE_NAME,
    siteUrl: `https://${ROOT_DOMAIN}`,
    recipient: payload.data.email,
    confirmationUrl: payload.data.url,
    token: payload.data.token,
    email: payload.data.email,
    newEmail: payload.data.new_email,
  }

  // Render React Email to HTML and plain text
  const html = await renderAsync(React.createElement(EmailTemplate, templateProps))
  const text = await renderAsync(React.createElement(EmailTemplate, templateProps), {
    plainText: true,
  })

  // Send email via Lovable Email API
  // The callback URL is provided in the payload by Lovable, ensuring correct routing
  // for both production and local development
  const callbackUrl = payload.data.callback_url
  if (!callbackUrl) {
    console.error('No callback_url in payload', { run_id })
    return new Response(JSON.stringify({ error: 'Missing callback_url in payload' }), {
      status: 400,
      headers: { ...corsHeaders, 'Content-Type': 'application/json' },
    })
  }

  let result: { message_id?: string }
  try {
    result = await sendLovableEmail(
      {
        run_id,
        to: payload.data.email,
        from: `${SITE_NAME} <noreply@${FROM_DOMAIN}>`,
        sender_domain: SENDER_DOMAIN,
        subject: EMAIL_SUBJECTS[emailType] || 'Notification',
        html,
        text,
        purpose: 'transactional',
      },
      { apiKey, sendUrl: callbackUrl }
    )
  } catch (error) {
    const message = error instanceof Error ? error.message : 'Failed to send email'
    console.error('Email API error', { error: message, run_id })
    return new Response(JSON.stringify({ error: 'Failed to send email' }), {
      status: 500,
      headers: { ...corsHeaders, 'Content-Type': 'application/json' },
    })
  }

  console.log('Email sent successfully', { message_id: result.message_id, run_id })

  return new Response(
    JSON.stringify({ success: true, message_id: result.message_id }),
    { status: 200, headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
  )
}

Deno.serve(async (req) => {
  const url = new URL(req.url)

  // Handle CORS preflight for main endpoint
  if (req.method === 'OPTIONS') {
    return new Response(null, { headers: corsHeaders })
  }

  // Route to preview handler for /preview path
  if (url.pathname.endsWith('/preview')) {
    return handlePreview(req)
  }

  // Main webhook handler
  try {
    return await handleWebhook(req)
  } catch (error) {
    console.error('Webhook handler error:', error)
    const message = error instanceof Error ? error.message : 'Unknown error'
    return new Response(JSON.stringify({ error: message }), {
      status: 500,
      headers: { ...corsHeaders, 'Content-Type': 'application/json' },
    })
  }
})

````


---

## auto-draw-scheduled

- **Purpose / logic:** Cron-driven automatic draws for competitions whose draw_date has passed and that are flagged for auto draw (player_board / standard auto-draw types). Picks winner, writes winners + draw_audits, sets status ended, emails winner/admin via send-email.
- **Trigger & auth:** pg_cron job 9 every 5 min with Bearer AUTO_DRAW_CRON_TOKEN (or CRON_SECRET_KEY); also callable by an authenticated admin.
- **Inputs:** {"time": now()} body (ignored).
- **Outputs / external calls:** DB writes (winners, draw_audits, competitions.status); calls send-email.

### `supabase/functions/auto-draw-scheduled/index.ts`

````ts
import { createClient } from "npm:@supabase/supabase-js@2.49.4";

const corsHeaders = {
  "Access-Control-Allow-Origin": "*",
  "Access-Control-Allow-Headers": "authorization, x-client-info, apikey, content-type",
};

const log = (step: string, details?: any) => {
  console.log(`[AUTO-DRAW] ${step}${details ? ` - ${JSON.stringify(details)}` : ""}`);
};

// Cryptographically secure random integer in [0, max)
function secureRandomInt(max: number): number {
  const buf = new Uint32Array(1);
  crypto.getRandomValues(buf);
  return buf[0] % max;
}

Deno.serve(async (req) => {
  if (req.method === "OPTIONS") return new Response(null, { headers: corsHeaders });

  // Auth: accept CRON_SECRET_KEY, service role, or admin JWT only.
  // The publishable anon key is NOT a valid authorizer here — it would let
  // any visitor trigger draws and spam emails.
  const authHeader = req.headers.get("Authorization") || "";
  const token = authHeader.replace(/^Bearer\s+/i, "");
  const cron = Deno.env.get("CRON_SECRET_KEY");
  const cronAlt = Deno.env.get("AUTO_DRAW_CRON_TOKEN");
  const svc = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY");
  const anon = Deno.env.get("SUPABASE_ANON_KEY");
  const pub = Deno.env.get("SUPABASE_PUBLISHABLE_KEY");
  let ok =
    (cron && token === cron) ||
    (cronAlt && token === cronAlt) ||
    (svc && token === svc);

  if (!ok && token) {
    // Try admin JWT fallback (used when an admin triggers the function from the UI)
    try {
      const userClient = createClient(
        Deno.env.get("SUPABASE_URL") ?? "",
        anon ?? pub ?? "",
        { global: { headers: { Authorization: authHeader } } }
      );
      const { data: { user } } = await userClient.auth.getUser();
      if (user) {
        const { data: isAdmin } = await userClient.rpc("has_role", {
          _user_id: user.id,
          _role: "admin",
        });
        if (isAdmin) ok = true;
      }
    } catch (_e) { /* ignore */ }
  }

  if (!ok) {
    return new Response(JSON.stringify({ error: "Unauthorized" }), {
      status: 401,
      headers: { ...corsHeaders, "Content-Type": "application/json" },
    });
  }

  const supabase = createClient(
    Deno.env.get("SUPABASE_URL") ?? "",
    Deno.env.get("SUPABASE_SERVICE_ROLE_KEY") ?? ""
  );

  const SUPABASE_URL = Deno.env.get("SUPABASE_URL") ?? "";
  const SERVICE_KEY = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY") ?? "";

  const now = new Date().toISOString();
  const results: any[] = [];

  try {
    // Find auto_draw competitions whose draw_date has passed and not yet drawn
    const { data: comps, error: compErr } = await supabase
      .from("competitions")
      .select("id, title, description, draw_date, status, competition_type, is_instant_win, random_ticket_allocation")
      .eq("status", "active")
      .eq("competition_type", "auto_draw")
      .eq("is_instant_win", false)
      .lte("draw_date", now);

    if (compErr) throw compErr;
    log("Eligible competitions", { count: comps?.length ?? 0 });

    for (const comp of comps ?? []) {
      try {
        // Skip if winner already exists (safety)
        const { data: existingWinner } = await supabase
          .from("winners")
          .select("id")
          .eq("competition_id", comp.id)
          .maybeSingle();
        if (existingWinner) {
          log("Winner already exists, skipping", { competitionId: comp.id });
          continue;
        }

        // Build ticket pool (same logic as draw-winner)
        type Ticket = {
          entryId: string;
          userId: string | null;
          name: string;
          email: string;
          type: "paid" | "postal";
          ticketNumber: number;
        };
        const tickets: Ticket[] = [];

        let maxPaid = 0;
        let paidCount = 0;
        let postalCount = 0;
        const profilesById: Record<string, { full_name?: string; email?: string }> = {};

        if (comp.random_ticket_allocation) {
          // Randomly allocated competitions: read individual ticket numbers
          const rows: any[] = [];
          const pageSize = 1000;
          let from = 0;
          while (true) {
            const { data, error } = await supabase
              .from("entry_tickets")
              .select("ticket_number, user_id, entry_id, postal_entry_id")
              .eq("competition_id", comp.id)
              .order("ticket_number", { ascending: true })
              .range(from, from + pageSize - 1);
            if (error) throw error;
            rows.push(...(data ?? []));
            if (!data || data.length < pageSize) break;
            from += pageSize;
          }

          const userIds = Array.from(new Set(rows.map((r: any) => r.user_id).filter(Boolean)));
          if (userIds.length) {
            const { data: profiles } = await supabase
              .from("profiles")
              .select("user_id, full_name, email")
              .in("user_id", userIds);
            for (const p of profiles ?? []) {
              profilesById[p.user_id] = { full_name: p.full_name, email: p.email };
            }
          }

          const postalById: Record<string, { full_name: string; email: string }> = {};
          const postalIds = Array.from(new Set(rows.map((r: any) => r.postal_entry_id).filter(Boolean)));
          if (postalIds.length) {
            const { data: postals } = await supabase
              .from("postal_entries")
              .select("id, full_name, email")
              .in("id", postalIds);
            for (const p of postals ?? []) {
              postalById[p.id] = { full_name: p.full_name, email: p.email };
            }
          }

          for (const row of rows) {
            const isPostal = !!row.postal_entry_id;
            const prof = row.user_id ? profilesById[row.user_id] : undefined;
            const postal = isPostal ? postalById[row.postal_entry_id] : undefined;
            tickets.push({
              entryId: row.entry_id ?? row.postal_entry_id,
              userId: row.user_id ?? null,
              name: (isPostal ? postal?.full_name : prof?.full_name) || "Unknown",
              email: (isPostal ? postal?.email : prof?.email) || "",
              type: isPostal ? "postal" : "paid",
              ticketNumber: row.ticket_number,
            });
            if (isPostal) postalCount++; else paidCount++;
            if (row.ticket_number > maxPaid) maxPaid = row.ticket_number;
          }
        } else {
          const { data: paidEntries } = await supabase
            .from("entries")
            .select("id, user_id, ticket_count, ticket_start_number")
            .eq("competition_id", comp.id)
            .eq("payment_status", "completed")
            .not("ticket_start_number", "is", null)
            .order("ticket_start_number", { ascending: true });

          const userIds = Array.from(
            new Set((paidEntries ?? []).map((e: any) => e.user_id).filter(Boolean))
          );
          if (userIds.length) {
            const { data: profiles } = await supabase
              .from("profiles")
              .select("user_id, full_name, email")
              .in("user_id", userIds);
            for (const p of profiles ?? []) {
              profilesById[p.user_id] = { full_name: p.full_name, email: p.email };
            }
          }

          for (const entry of paidEntries ?? []) {
            const prof = profilesById[entry.user_id] || {};
            for (let i = 0; i < entry.ticket_count; i++) {
              const num = entry.ticket_start_number + i;
              tickets.push({
                entryId: entry.id,
                userId: entry.user_id,
                name: prof.full_name || "Unknown",
                email: prof.email || "",
                type: "paid",
                ticketNumber: num,
              });
              if (num > maxPaid) maxPaid = num;
            }
            paidCount += entry.ticket_count;
          }

          const { data: postalEntries } = await supabase
            .from("postal_entries")
            .select("id, full_name, email, ticket_count, ticket_start_number")
            .eq("competition_id", comp.id)
            .order("created_at", { ascending: true });

          let postalNum = Math.max(maxPaid, ...(postalEntries ?? []).map(e => e.ticket_start_number == null ? 0 : e.ticket_start_number + e.ticket_count - 1)) + 1;
          for (const e of postalEntries ?? []) {
            for (let i = 0; i < e.ticket_count; i++) {
              tickets.push({
                entryId: e.id,
                userId: null,
                name: e.full_name,
                email: e.email,
                type: "postal",
                ticketNumber: e.ticket_start_number == null ? postalNum++ : e.ticket_start_number + i,
              });
            }
            postalCount += e.ticket_count;
          }
        }

        const total = tickets.length;
        if (total === 0) {
          log("No tickets, marking ended without winner", { competitionId: comp.id });
          await supabase.from("competitions").update({ status: "ended" }).eq("id", comp.id);
          results.push({ id: comp.id, title: comp.title, drawn: false, reason: "no_tickets" });
          continue;
        }

        // Secure random selection
        const winnerIdx = secureRandomInt(total);
        const winner = tickets[winnerIdx];

        const auditData = `${comp.id}|${winner.ticketNumber}|${winner.entryId}|${new Date().toISOString()}`;
        const hashBuf = await crypto.subtle.digest("SHA-256", new TextEncoder().encode(auditData));
        const auditHash = Array.from(new Uint8Array(hashBuf), (b) =>
          b.toString(16).padStart(2, "0")
        ).join("");

        const { data: newWinner, error: winErr } = await supabase
          .from("winners")
          .insert({
            user_id: winner.userId,
            competition_id: comp.id,
            winner_name: winner.name,
            prize_description: comp.title,
            announced: false,
          })
          .select()
          .single();

        if (winErr) {
          log("Failed to insert winner", { competitionId: comp.id, error: winErr.message });
          results.push({ id: comp.id, drawn: false, error: winErr.message });
          continue;
        }

        const { error: auditErr } = await supabase.from("draw_audits").insert({
          competition_id: comp.id,
          performed_by_admin_id: "00000000-0000-0000-0000-000000000000",
          total_paid_entries: paidCount,
          total_postal_entries: postalCount,
          total_tickets: total,
          random_seed: "scheduled-auto-draw-csprng",
          winning_ticket_number: winner.ticketNumber,
          winner_type: winner.type,
          winner_entry_id: winner.entryId,
          winner_name: winner.name,
          winner_email: winner.email,
          audit_hash: auditHash,
          is_published: false,
        });
        if (auditErr) log("Audit insert failed (non-fatal)", { error: auditErr.message });

        await supabase.from("competitions").update({ status: "ended" }).eq("id", comp.id);

        // Notify winner
        if (winner.email) {
          try {
            await fetch(`${SUPABASE_URL}/functions/v1/send-email`, {
              method: "POST",
              headers: {
                "Content-Type": "application/json",
                Authorization: `Bearer ${SERVICE_KEY}`,
              },
              body: JSON.stringify({
                type: "winner_notification",
                to: winner.email,
                data: {
                  competitionTitle: comp.title,
                  prizeDescription: comp.title,
                  winnerName: winner.name,
                  userId: winner.userId,
                },
              }),
            });
          } catch (err) {
            log("Winner email failed", { err: String(err) });
          }
        }

        // Notify all unique entrants (paid + postal)
        const entrantEmails = new Set<string>();
        for (const t of tickets) {
          if (t.email && t.email !== winner.email) entrantEmails.add(t.email.toLowerCase());
        }
        log("Notifying entrants", { count: entrantEmails.size });
        for (const email of entrantEmails) {
          try {
            await fetch(`${SUPABASE_URL}/functions/v1/send-email`, {
              method: "POST",
              headers: {
                "Content-Type": "application/json",
                Authorization: `Bearer ${SERVICE_KEY}`,
              },
              body: JSON.stringify({
                type: "draw_complete",
                to: email,
                data: {
                  competitionTitle: comp.title,
                  winningTicketNumber: winner.ticketNumber,
                  winnerName: winner.name,
                },
              }),
            });
          } catch (err) {
            log("Entrant email failed", { email, err: String(err) });
          }
        }

        results.push({
          id: comp.id,
          title: comp.title,
          drawn: true,
          winnerName: winner.name,
          ticketNumber: winner.ticketNumber,
          totalTickets: total,
          entrantsNotified: entrantEmails.size,
        });
      } catch (innerErr: any) {
        log("Competition draw error", { id: comp.id, error: innerErr.message });
        results.push({ id: comp.id, drawn: false, error: innerErr.message });
      }
    }

    return new Response(
      JSON.stringify({ success: true, processed: results.length, results }),
      { headers: { ...corsHeaders, "Content-Type": "application/json" }, status: 200 }
    );
  } catch (err: any) {
    log("Fatal error", { error: err.message });
    return new Response(JSON.stringify({ error: err.message }), {
      status: 500,
      headers: { ...corsHeaders, "Content-Type": "application/json" },
    });
  }
});

````


---

## cashflows-webhook

- **Purpose / logic:** Cashflows payment notification handler. Logs to webhook_logs, finds order, RE-VERIFIES payment by calling Cashflows RetrievePaymentJob API, maps status, and if paid creates entries (ticket numbers assigned by DB triggers), deducts site credit, runs process_instant_win per instant-win entry, marks metadata.instantWinProcessedAt, sends confirmation emails, handles player-board space sale + auto draw.
- **Trigger & auth:** Cashflows server-to-server POST. verify_jwt=false. No HMAC signature check on inbound body; trust comes from server-side re-verification against Cashflows API.
- **Inputs:** Cashflows notify body: paymentJobReference, paymentReference, notifyType, orderNumber.
- **Outputs / external calls:** Always 200 {received:true} (so Cashflows does not retry endlessly); DB writes; send-email calls.

### `supabase/functions/cashflows-webhook/index.ts`

````ts
import { serve } from "https://deno.land/std@0.190.0/http/server.ts";
import { createClient } from "npm:@supabase/supabase-js@2.49.4";

const corsHeaders = {
  "Access-Control-Allow-Origin": "*",
  "Access-Control-Allow-Headers": "authorization, x-client-info, apikey, content-type, x-supabase-client-platform, x-supabase-client-platform-version, x-supabase-client-runtime, x-supabase-client-runtime-version",
};

const log = (step: string, details?: any) => {
  const d = details ? ` - ${JSON.stringify(details)}` : '';
  console.log(`[CASHFLOWS-WEBHOOK] ${step}${d}`);
};

function getBaseUrl(): string {
  const env = Deno.env.get("CASHFLOWS_ENV") || "integration";
  const override = Deno.env.get("CASHFLOWS_BASE_URL");
  if (override) return override.replace(/\/$/, '');
  return env === "production"
    ? "https://gateway.cashflows.com"
    : "https://gateway-int.cashflows.com";
}

async function createHash(apiKey: string, bodyString: string): Promise<string> {
  const encoder = new TextEncoder();
  const data = encoder.encode(apiKey + bodyString);
  const hashBuffer = await crypto.subtle.digest("SHA-512", data);
  return Array.from(new Uint8Array(hashBuffer)).map(b => b.toString(16).padStart(2, '0')).join('');
}

function mapCashflowsStatus(cfStatus: string): string {
  const s = (cfStatus || "").toLowerCase();
  if (s === "paid" || s === "captured") return "paid";
  if (s === "failed" || s === "declined") return "failed";
  if (s === "cancelled") return "cancelled";
  if (s === "expired") return "expired";
  return "pending_payment";
}

serve(async (req) => {
  if (req.method === "OPTIONS") {
    return new Response(null, { headers: corsHeaders });
  }

  const supabaseAdmin = createClient(
    Deno.env.get("SUPABASE_URL") ?? "",
    Deno.env.get("SUPABASE_SERVICE_ROLE_KEY") ?? "",
    { auth: { persistSession: false } }
  );

  try {
    log("Webhook received");

    const body = await req.json();
    log("Webhook payload", body);

    const {
      notifyType,
      paymentJobReference,
      paymentReference,
      orderNumber,
    } = body;

    // Log webhook (capture id so we can update THIS row later, not every cashflows row)
    const { data: webhookLogRow } = await supabaseAdmin
      .from("webhook_logs")
      .insert({
        source: "cashflows",
        event_type: notifyType || "unknown",
        payload: body,
      })
      .select("id")
      .single();
    const webhookLogId: string | null = webhookLogRow?.id ?? null;

    if (!paymentJobReference) {
      log("No paymentJobReference in webhook");
      return new Response(JSON.stringify({ received: true }), {
        headers: { ...corsHeaders, "Content-Type": "application/json" },
      });
    }

    // Find order by reference or orderNumber
    let orderQuery = supabaseAdmin.from("orders").select("*");
    if (orderNumber) {
      orderQuery = orderQuery.eq("order_number", orderNumber);
    } else {
      orderQuery = orderQuery.eq("cashflows_payment_job_reference", paymentJobReference);
    }

    const { data: orders, error: orderError } = await orderQuery.limit(1);
    
    if (orderError || !orders || orders.length === 0) {
      log("Order not found", { paymentJobReference, orderNumber });
      return new Response(JSON.stringify({ received: true, warning: "order not found" }), {
        headers: { ...corsHeaders, "Content-Type": "application/json" },
      });
    }

    const order = orders[0];
    log("Order found", { orderId: order.id, orderNumber: order.order_number });

    // Update references
    await supabaseAdmin.from("orders").update({
      cashflows_payment_job_reference: paymentJobReference,
      cashflows_payment_reference: paymentReference || order.cashflows_payment_reference,
    }).eq("id", order.id);

    // Verify with Cashflows RetrievePaymentJob
    const apiKey = Deno.env.get("CASHFLOWS_API_KEY");
    const configId = Deno.env.get("CASHFLOWS_CONFIGURATION_ID");

    if (apiKey && configId && paymentReference) {
      const baseUrl = getBaseUrl();
      const hash = await createHash(apiKey, "");

      const verifyUrl = `${baseUrl}/api/gateway/payment-jobs/${paymentJobReference}/payments/${paymentReference}`;
      log("Verifying with Cashflows", { verifyUrl });

      const cfResponse = await fetch(verifyUrl, {
        method: "GET",
        headers: {
          "ConfigurationId": configId,
          "Hash": hash,
        },
      });

      const cfData = await cfResponse.json();
      log("Cashflows verify response", { status: cfResponse.status, data: cfData });

      // Cashflows wraps response in a "data" object
      const cfInner = cfData.data || cfData;
      const cfStatus = cfInner.status || cfInner.paymentStatus || notifyType;
      const mappedStatus = mapCashflowsStatus(cfStatus);

      // Update order status
      await supabaseAdmin.from("orders").update({
        status: mappedStatus,
        cashflows_last_status: cfStatus,
      }).eq("id", order.id);

      // If paid, create entries now and send confirmation email
      if (mappedStatus === "paid") {
        // Idempotency check: skip if order already has entries
        const { count: existingEntryCount } = await supabaseAdmin
          .from("entries")
          .select("id", { count: "exact", head: true })
          .eq("payment_intent_id", paymentReference)
          .eq("payment_status", "completed");

        if (existingEntryCount && existingEntryCount > 0) {
          log("Entries already exist for this payment, skipping", { paymentReference, existingEntryCount });
        } else {

        const metadata = order.metadata as any;
        const items = metadata?.items || [];
        
        const standardItems = items.filter((i: any) => i.competitionType !== 'player_board');
        
        if (standardItems.length > 0) {
          log("Creating entries for paid order", { orderNumber: order.order_number, items: standardItems });

          // Fetch competition details for pricing
          const compIds = standardItems.map((i: any) => i.competitionId);
          const { data: comps } = await supabaseAdmin
            .from("competitions")
            .select("id, title, ticket_price, draw_date, buy_quantity, free_quantity")
            .in("id", compIds);

          const compMap = new Map((comps || []).map((c: any) => [c.id, c]));

          // Calculate amount_paid proportionally from the order's actual total (which includes discounts)
          const undiscountedTotal = standardItems.reduce((sum: number, item: any) => {
            if (item.bundlePrice != null) return sum + Number(item.bundlePrice);
            const comp = compMap.get(item.competitionId);
            return sum + (comp ? comp.ticket_price * item.ticketCount : 0);
          }, 0);
          const discountRatio = undiscountedTotal > 0 ? order.amount / undiscountedTotal : 1;

          const entryInserts = standardItems.map((item: any) => {
            const comp = compMap.get(item.competitionId);
            const totalTickets = item.totalTickets || item.ticketCount;
            const rawPrice = item.bundlePrice != null
              ? Number(item.bundlePrice)
              : (comp ? comp.ticket_price * item.ticketCount : 0);
            const discountedPrice = Math.round(rawPrice * discountRatio * 100) / 100;
            return {
              user_id: order.user_id,
              competition_id: item.competitionId,
              ticket_count: totalTickets,
              amount_paid: discountedPrice,
              payment_status: "completed",
              payment_intent_id: paymentReference,
            };
          });

          let { data: createdEntries, error: entryError } = await supabaseAdmin
            .from("entries")
            .insert(entryInserts)
            .select("id, ticket_count, ticket_start_number, competition_id");

          const failedItems: { competitionId: string; error: string }[] = [];

          if (entryError) {
            // A batch insert fails as a whole (e.g. one competition ran out of
            // tickets), which would leave a paid customer with NO entries at all.
            // Retry per item so every competition that can still be allocated is.
            log("Batch entry insert failed, retrying per item", { error: entryError.message });
            const salvaged: any[] = [];
            for (const row of entryInserts) {
              const { data: one, error: oneErr } = await supabaseAdmin
                .from("entries")
                .insert(row)
                .select("id, ticket_count, ticket_start_number, competition_id")
                .single();
              if (oneErr) {
                failedItems.push({ competitionId: row.competition_id, error: oneErr.message });
                log("Entry creation failed for item", { competitionId: row.competition_id, error: oneErr.message });
              } else if (one) {
                salvaged.push(one);
              }
            }
            createdEntries = salvaged;
            entryError = salvaged.length > 0 ? null : entryError;
          }

          if (failedItems.length > 0) {
            // Flag the order so it is visible to admins instead of failing
            // silently; the customer has been charged for these tickets.

            await supabaseAdmin.from("orders").update({
              metadata: { ...(order.metadata as any), allocationFailures: failedItems },
            }).eq("id", order.id);

            if (webhookLogId) {
              await supabaseAdmin.from("webhook_logs").update({
                processing_error: `Paid order ${order.order_number} could not allocate tickets: ${failedItems.map((f) => `${f.competitionId}: ${f.error}`).join("; ")}`,
              }).eq("id", webhookLogId);
            }
          }

          if (entryError) {
            log("Failed to create entries", { error: entryError.message });
          } else if (createdEntries && createdEntries.length > 0) {

            // Update order with entry IDs
            const entryIds = createdEntries.map((e: any) => e.id);
            await supabaseAdmin.from("orders").update({ entry_ids: entryIds }).eq("id", order.id);

            // Deduct site credit if used for this order (atomic to prevent over-spending)
            const orderMetadata = order.metadata as any;
            if (orderMetadata?.creditUsed && orderMetadata.creditUsed > 0) {
              const { data: deducted, error: deductErr } = await supabaseAdmin.rpc('deduct_site_credit', {
                p_user_id: order.user_id,
                p_amount: orderMetadata.creditUsed,
                p_reason: `Order ${order.order_number}`,
                p_order_id: order.id,
              });
              if (deductErr) {
                log("Credit deduction error", { error: deductErr.message });
              } else {
                log("Credit deducted", { userId: order.user_id, requested: orderMetadata.creditUsed, actual: deducted });
              }
            }

            // Process instant win for entries belonging to instant win competitions
            const prizesByCompetition: Record<string, string[]> = {};
            const instantWinCompIds = new Set<string>();
            let hasInstantWin = false;
            for (const entry of createdEntries) {
              const item = standardItems.find((i: any) => i.competitionId === entry.competition_id);
              if (item?.isInstantWin) {
                hasInstantWin = true;
                instantWinCompIds.add(entry.competition_id);
                try {
                  for (let t = 0; t < entry.ticket_count; t++) {
                    const { data: iwResult, error: iwError } = await supabaseAdmin.rpc('process_instant_win', {
                      p_entry_id: entry.id,
                      p_competition_id: entry.competition_id,
                      p_user_id: order.user_id,
                    });
                    if (iwError) {
                      log("Instant win error", { entryId: entry.id, ticket: t, error: iwError.message });
                    } else {
                      log("Instant win result", { entryId: entry.id, ticket: t, result: iwResult });
                      const r = iwResult as any;
                      if (r?.is_winner && r?.prize_name && r.prize_name.toLowerCase() !== 'no prize' && (r.prize_value ?? 0) > 0) {
                        (prizesByCompetition[entry.competition_id] ||= []).push(r.prize_name);
                      }
                    }
                  }
                } catch (iwErr) {
                  log("Instant win exception", { entryId: entry.id, error: String(iwErr) });
                }
              }
            }

            // Mark instant-win processing as finished so the success page only
            // reveals results once every ticket has actually been drawn.
            if (hasInstantWin) {
              const { data: freshOrder } = await supabaseAdmin
                .from("orders").select("metadata").eq("id", order.id).maybeSingle();
              await supabaseAdmin.from("orders").update({
                metadata: {
                  ...((freshOrder?.metadata as any) ?? (order.metadata as any) ?? {}),
                  instantWinProcessedAt: new Date().toISOString(),
                },
              }).eq("id", order.id);
            }

            // Randomly allocated competitions store each ticket number individually
            const randomNumbersByEntry: Record<string, number[]> = {};
            const { data: allocatedTickets } = await supabaseAdmin
              .from("entry_tickets")
              .select("entry_id, ticket_number")
              .in("entry_id", createdEntries.map((e: any) => e.id))
              .order("ticket_number", { ascending: true });
            for (const t of allocatedTickets ?? []) {
              if (!t.entry_id) continue;
              (randomNumbersByEntry[t.entry_id] ||= []).push(t.ticket_number);
            }

            const formatEntryTickets = (entry: any): string | null => {
              const randomNums = randomNumbersByEntry[entry.id];
              if (randomNums?.length) {
                const shown = randomNums.slice(0, 25).map((n) => `#${n}`).join(", ");
                return randomNums.length > 25 ? `${shown} +${randomNums.length - 25} more` : shown;
              }
              if (entry.ticket_start_number != null) {
                const start = entry.ticket_start_number;
                const end = start + entry.ticket_count - 1;
                return start === end ? `#${start}` : `#${start}–#${end}`;
              }
              return null;
            };

            const fmtDate = (d?: string | null) => d
              ? new Date(d).toLocaleDateString("en-GB", { weekday: "long", year: "numeric", month: "long", day: "numeric" })
              : "TBC";

            // Build one email per instant-win competition (its own results only),
            // plus a single entry confirmation for all regular competitions, so a
            // win in one competition never reads as a win for the others.
            type EmailJob = { type: string; data: Record<string, any> };
            const jobs: EmailJob[] = [];
            const regularEntries = createdEntries.filter((e: any) => !instantWinCompIds.has(e.competition_id));

            for (const compId of instantWinCompIds) {
              const compEntries = createdEntries.filter((e: any) => e.competition_id === compId);
              const ranges = compEntries.map(formatEntryTickets).filter(Boolean) as string[];
              const prizes = prizesByCompetition[compId] ?? [];
              jobs.push({
                type: "instant_win_confirmation",
                data: {
                  competitionTitle: (compMap.get(compId) as any)?.title?.trim() ?? "Instant Win",
                  ticketCount: compEntries.reduce((s: number, e: any) => s + e.ticket_count, 0),
                  ticketNumbers: ranges.length ? ranges.join(", ") : undefined,
                  isInstantWin: true,
                  instantWinPrizes: prizes.length ? prizes : undefined,
                },
              });
            }

            if (regularEntries.length > 0) {
              const ranges = regularEntries.map(formatEntryTickets).filter(Boolean) as string[];
              const regularComps = [...new Set(regularEntries.map((e: any) => e.competition_id))]
                .map((id) => compMap.get(id) as any).filter(Boolean);
              jobs.push({
                type: "entry_confirmation",
                data: {
                  competitionTitle: regularComps.map((c: any) => c.title?.trim()).join(", "),
                  ticketCount: regularEntries.reduce((s: number, e: any) => s + e.ticket_count, 0),
                  ticketNumbers: ranges.length ? ranges.join(", ") : undefined,
                  drawDate: fmtDate(regularComps[0]?.draw_date),
                },
              });
            }

            // Get user's email for confirmation
            const { data: userProfile } = await supabaseAdmin
              .from("profiles")
              .select("email, full_name")
              .eq("user_id", order.user_id)
              .single();

            if (userProfile?.email) {
              const siteBaseUrl = Deno.env.get("SITE_BASE_URL") || "https://vaultninecompetitions.co.uk";
              for (const job of jobs) {
                try {
                  const emailResp = await fetch(`${Deno.env.get("SUPABASE_URL")}/functions/v1/send-email`, {
                    method: "POST",
                    headers: {
                      "Content-Type": "application/json",
                      "Authorization": `Bearer ${Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")}`,
                    },
                    body: JSON.stringify({
                      type: job.type,
                      to: userProfile.email,
                      data: { userId: order.user_id, siteUrl: siteBaseUrl, ...job.data },
                    }),
                  });
                  if (!emailResp.ok) {
                    const errBody = await emailResp.text();
                    log("Confirmation email FAILED", { type: job.type, status: emailResp.status, body: errBody });
                  } else {
                    log("Confirmation email sent", { type: job.type, email: userProfile.email });
                  }
                } catch (emailErr) {
                  log("Failed to send confirmation email", { type: job.type, error: String(emailErr) });
                }
              }
            }
          }
        }

        // Deduct site credit if used AND no standard items handled it above (atomic)
        if (standardItems.length === 0) {
          const orderMetaCred = order.metadata as any;
          if (orderMetaCred?.creditUsed && orderMetaCred.creditUsed > 0) {
            const { data: deducted2, error: deductErr2 } = await supabaseAdmin.rpc('deduct_site_credit', {
              p_user_id: order.user_id,
              p_amount: orderMetaCred.creditUsed,
              p_reason: `Order ${order.order_number}`,
              p_order_id: order.id,
            });
            if (deductErr2) {
              log("Credit deduction error (non-standard)", { error: deductErr2.message });
            } else {
              log("Credit deducted (non-standard path)", { userId: order.user_id, requested: orderMetaCred.creditUsed, actual: deducted2 });
            }
          }
        }

        // Handle player board orders (INSIDE idempotency block to prevent duplicates on webhook retry)
        const orderMetadata2 = order.metadata as any;
        if (orderMetadata2?.type === 'player_board' && orderMetadata2?.spaceIds?.length > 0) {
          log("Processing player board order", { spaceIds: orderMetadata2.spaceIds });

          const compId = orderMetadata2.competitionId;

          const { data: pbComp } = await supabaseAdmin
            .from("competitions")
            .select("id, title, description")
            .eq("id", compId)
            .single();

          // Mark spaces as sold (also accept 'available' in case reservation expired)
          const { error: spaceUpdateErr } = await supabaseAdmin
            .from("competition_spaces")
            .update({ status: "sold", reserved_at: null, reserved_until: null, updated_at: new Date().toISOString() })
            .in("id", orderMetadata2.spaceIds)
            .in("status", ["reserved", "available"]);

          if (spaceUpdateErr) {
            log("Failed to update spaces", { error: spaceUpdateErr.message });
          }

          const { data: purchasedSpaces } = await supabaseAdmin
            .from("competition_spaces")
            .select("space_number, player_name")
            .in("id", orderMetadata2.spaceIds)
            .order("space_number", { ascending: true });

          const spaceEntries = orderMetadata2.spaceIds.map((sid: string) => ({
            user_id: order.user_id,
            competition_id: compId,
            ticket_count: 1,
            amount_paid: order.amount / orderMetadata2.spaceIds.length,
            payment_status: "completed",
            payment_intent_id: paymentReference,
          }));

          const { data: pbEntries, error: pbEntryErr } = await supabaseAdmin
            .from("entries")
            .insert(spaceEntries)
            .select("id");

          if (pbEntryErr) {
            log("Failed to create player board entries", { error: pbEntryErr.message });
          } else if (pbEntries) {
            for (let i = 0; i < orderMetadata2.spaceIds.length && i < pbEntries.length; i++) {
              await supabaseAdmin.from("competition_spaces")
                .update({ entry_id: pbEntries[i].id })
                .eq("id", orderMetadata2.spaceIds[i]);
            }

            const entryIds = pbEntries.map((e: any) => e.id);
            await supabaseAdmin.from("orders").update({ entry_ids: entryIds }).eq("id", order.id);

            const { data: userProfile } = await supabaseAdmin
              .from("profiles")
              .select("email, full_name")
              .eq("user_id", order.user_id)
              .single();

            if (userProfile?.email) {
              try {
                const siteBaseUrl = Deno.env.get("SITE_BASE_URL") || "https://vaultninecompetitions.co.uk";
                const spacesList = purchasedSpaces
                  ? purchasedSpaces.map((s: any) => `#${s.space_number} — ${s.player_name}`).join(", ")
                  : orderMetadata2.spaceIds.length + " spaces";

                const pbEmailResp = await fetch(`${Deno.env.get("SUPABASE_URL")}/functions/v1/send-email`, {
                  method: "POST",
                  headers: {
                    "Content-Type": "application/json",
                    "Authorization": `Bearer ${Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")}`,
                  },
                  body: JSON.stringify({
                    type: "player_board_confirmation",
                    to: userProfile.email,
                    data: {
                      customerName: userProfile.full_name || "there",
                      competitionTitle: pbComp?.title || orderMetadata2.competitionTitle || "Player Board Competition",
                      spaceCount: orderMetadata2.spaceIds.length,
                      spacesList,
                      orderNumber: order.order_number,
                      amountPaid: `£${Number(order.amount).toFixed(2)}`,
                      siteUrl: siteBaseUrl,
                    },
                  }),
                });
                if (!pbEmailResp.ok) {
                  const errBody = await pbEmailResp.text();
                  log("Player board confirmation email FAILED", { status: pbEmailResp.status, body: errBody });
                } else {
                  log("Player board confirmation email sent", { email: userProfile.email });
                }
              } catch (emailErr) {
                log("Failed to send player board confirmation email", { error: String(emailErr) });
              }
            }

            const { count: totalSpaces } = await supabaseAdmin.from("competition_spaces")
              .select("id", { count: "exact" }).eq("competition_id", compId);
            const { count: soldSpaces } = await supabaseAdmin.from("competition_spaces")
              .select("id", { count: "exact" }).eq("competition_id", compId).in("status", ["sold", "winner"]);

            log("Player board status", { totalSpaces, soldSpaces });

            if (soldSpaces === totalSpaces && totalSpaces && totalSpaces > 0) {
              log("All spaces sold - triggering auto-draw");
              const { data: drawResult } = await supabaseAdmin.rpc('player_board_auto_draw', { p_competition_id: compId });
              log("Auto-draw result", drawResult);

              if (drawResult?.success && drawResult?.winner_email) {
                try {
                  const siteBaseUrl = Deno.env.get("SITE_BASE_URL") || "https://vaultninecompetitions.co.uk";
                  const winnerEmailResp = await fetch(`${Deno.env.get("SUPABASE_URL")}/functions/v1/send-email`, {
                    method: "POST",
                    headers: {
                      "Content-Type": "application/json",
                      "Authorization": `Bearer ${Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")}`,
                    },
                    body: JSON.stringify({
                      type: "player_board_winner",
                      to: drawResult.winner_email,
                      data: {
                        winnerName: drawResult.winner_name,
                        competitionTitle: pbComp?.title || orderMetadata2.competitionTitle || "Player Board Competition",
                        prizeDescription: pbComp?.description || "Amazing Prize",
                        winningSpaceNumber: drawResult.winner_space_number,
                        winningPlayerName: drawResult.winner_player_name,
                        siteUrl: siteBaseUrl,
                      },
                    }),
                  });
                  if (!winnerEmailResp.ok) {
                    const errBody = await winnerEmailResp.text();
                    log("Winner email FAILED", { status: winnerEmailResp.status, body: errBody });
                  } else {
                    log("Winner email sent", { email: drawResult.winner_email });
                  }
                } catch (emailErr) {
                  log("Failed to send winner email", { error: String(emailErr) });
                }
              }
            }
          }
        }
        } // end idempotency else block
      }

      // Update webhook log (target THIS log row by id — previously updated every cashflows row)
      if (webhookLogId) {
        await supabaseAdmin.from("webhook_logs").update({
          processing_result: `Status: ${mappedStatus} (CF: ${cfStatus})`,
        }).eq("id", webhookLogId);
      }

    } else {
      // Refuse to update order status without a verified Cashflows API callout.
      // Previously we trusted the attacker-controlled notifyType field when
      // paymentReference/API creds were missing, which allowed order-status
      // manipulation (e.g. forcing 'cancelled') by anyone who guessed a ref.
      log("Refusing to update order without Cashflows verification", {
        hasApiKey: !!apiKey,
        hasConfigId: !!configId,
        hasPaymentReference: !!paymentReference,
      });
      if (webhookLogId) {
        await supabaseAdmin.from("webhook_logs").update({
          processing_result: "Rejected: missing paymentReference or Cashflows credentials; cannot verify",
        }).eq("id", webhookLogId);
      }
      return new Response(
        JSON.stringify({ error: "Unverifiable webhook: paymentReference required" }),
        { status: 400, headers: { ...corsHeaders, "Content-Type": "application/json" } },
      );
    }


    return new Response(JSON.stringify({
      received: true,
      paymentJobReference,
      paymentReference,
    }), {
      headers: { ...corsHeaders, "Content-Type": "application/json" },
    });

  } catch (error) {
    const msg = error instanceof Error ? error.message : String(error);
    log("ERROR", { message: msg });

    // Still log the error
    try {
      await supabaseAdmin.from("webhook_logs").insert({
        source: "cashflows",
        event_type: "error",
        processing_error: msg,
      });
    } catch {}

    return new Response(JSON.stringify({ received: true, error: msg }), {
      headers: { ...corsHeaders, "Content-Type": "application/json" },
      status: 200, // Return 200 so Cashflows doesn't retry
    });
  }
});

````


---

## create-cashflows-checkout

- **Purpose / logic:** Main basket checkout. Authenticates user, validates items, loads competitions server-side, enforces status/draw cutoff/capacity (including bonus tickets and 30-min pending holds), prices server-side (sale price, bundle tiers, Buy X Get Y, multipliers), applies promo (consume_promo_code), applies site credit, checks responsible-play limits, creates order (pending_payment) and calls Cashflows payment-jobs API to get hosted checkout URL. Free / fully-credit-paid orders are completed immediately without Cashflows.
- **Trigger & auth:** Browser via supabase.functions.invoke from CartSheet. verify_jwt=false but validates bearer token with auth.getUser.
- **Inputs:** { items:[{competitionId, ticketCount, competitionType, spaceIds?, bundleTierIndex?}], promoCode?, creditAmount? }
- **Outputs / external calls:** { url (Cashflows action URL), orderNumber } or { success, orderNumber, instantWinResults } for credit/free orders; 4xx with error text.

### `supabase/functions/create-cashflows-checkout/index.ts`

````ts
import { serve } from "https://deno.land/std@0.190.0/http/server.ts";
import { createClient } from "npm:@supabase/supabase-js@2.49.4";

const corsHeaders = {
  "Access-Control-Allow-Origin": "*",
  "Access-Control-Allow-Headers": "authorization, x-client-info, apikey, content-type, x-supabase-client-platform, x-supabase-client-platform-version, x-supabase-client-runtime, x-supabase-client-runtime-version",
};

const log = (step: string, details?: any) => {
  const d = details ? ` - ${JSON.stringify(details)}` : '';
  console.log(`[CASHFLOWS-CHECKOUT] ${step}${d}`);
};

// Helper: wrap a promise with a timeout
function withTimeout<T>(promise: Promise<T>, ms: number, label: string): Promise<T> {
  return Promise.race([
    promise,
    new Promise<T>((_, reject) =>
      setTimeout(() => reject(new Error(`Timeout after ${ms}ms: ${label}`)), ms)
    ),
  ]);
}

const DB_TIMEOUT = 8000; // 8s for DB operations
const API_TIMEOUT = 15000; // 15s for Cashflows API

function getBaseUrl(): string {
  const env = Deno.env.get("CASHFLOWS_ENV") || "integration";
  const override = Deno.env.get("CASHFLOWS_BASE_URL");
  if (override) return override.replace(/\/$/, '');
  return env === "production"
    ? "https://gateway.cashflows.com"
    : "https://gateway-int.cashflows.com";
}

async function createHash(apiKey: string, bodyString: string): Promise<string> {
  const encoder = new TextEncoder();
  const data = encoder.encode(apiKey + bodyString);
  const hashBuffer = await crypto.subtle.digest("SHA-512", data);
  const hashArray = Array.from(new Uint8Array(hashBuffer));
  return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
}

function generateOrderNumber(): string {
  const ts = Date.now().toString(36).toUpperCase();
  const rand = Math.random().toString(36).substring(2, 6).toUpperCase();
  return `VN-${ts}-${rand}`;
}

const UUID_REGEX = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;


interface CartItem {
  competitionId: string;
  ticketCount: number;
  competitionType?: string;
  spaceIds?: string[];
}

const RESERVATION_MINUTES = 15;

// Bonus tickets: a ticket multiplier (min qty -> xN) replaces any Buy X Get Y deal.
function calcBonusTickets(comp: any, ticketCount: number): number {
  const minQty = Number(comp?.multiplier_min_tickets) || 0;
  const mult = Number(comp?.multiplier_value) || 0;
  if (minQty > 0 && mult > 1) {
    return ticketCount >= minQty ? Math.floor(ticketCount * (mult - 1)) : 0;
  }
  const buyQty = Number(comp?.buy_quantity) || 0;
  const freeQty = Number(comp?.free_quantity) || 0;
  if (buyQty > 0 && freeQty > 0) return Math.floor(ticketCount / buyQty) * freeQty;
  return 0;
}

serve(async (req) => {
  if (req.method === "OPTIONS") {
    return new Response(null, { headers: corsHeaders });
  }

  const supabaseAdmin = createClient(
    Deno.env.get("SUPABASE_URL") ?? "",
    Deno.env.get("SUPABASE_SERVICE_ROLE_KEY") ?? "",
    { auth: { persistSession: false } }
  );

  const supabaseClient = createClient(
    Deno.env.get("SUPABASE_URL") ?? "",
    Deno.env.get("SUPABASE_ANON_KEY") ?? ""
  );

  try {
    log("Function started");

    const configId = Deno.env.get("CASHFLOWS_CONFIGURATION_ID");
    const apiKey = Deno.env.get("CASHFLOWS_API_KEY");
    if (!configId || !apiKey) throw new Error("Cashflows credentials not configured");

    const siteBaseUrl = Deno.env.get("SITE_BASE_URL") || "https://vaultninecompetitions.co.uk";

    // Auth
    const authHeader = req.headers.get("Authorization");
    if (!authHeader) {
      return new Response(JSON.stringify({ error: "Unauthorized" }), {
        headers: { ...corsHeaders, "Content-Type": "application/json" }, status: 401
      });
    }

    const token = authHeader.replace("Bearer ", "");
    const { data: userData, error: userError } = await withTimeout(
      supabaseClient.auth.getUser(token),
      DB_TIMEOUT,
      "auth.getUser"
    );
    if (userError || !userData.user?.email) {
      return new Response(JSON.stringify({ error: "Authentication failed" }), {
        headers: { ...corsHeaders, "Content-Type": "application/json" }, status: 401
      });
    }
    const user = userData.user;
    log("User authenticated", { userId: user.id });

    // Parse body
    let body: any;
    try { body = await req.json(); } catch {
      return new Response(JSON.stringify({ error: "Invalid JSON" }), {
        headers: { ...corsHeaders, "Content-Type": "application/json" }, status: 400
      });
    }

    const { items, promoCode, creditAmount } = body;
    if (!Array.isArray(items) || items.length === 0) {
      return new Response(JSON.stringify({ error: "Invalid cart items" }), {
        headers: { ...corsHeaders, "Content-Type": "application/json" }, status: 400
      });
    }

    // Validate items
    const validatedItems: CartItem[] = [];
    for (const item of items) {
      if (!item.competitionId || !UUID_REGEX.test(item.competitionId)) {
        return new Response(JSON.stringify({ error: "Invalid competition ID" }), {
          headers: { ...corsHeaders, "Content-Type": "application/json" }, status: 400
        });
      }
      if (!Number.isInteger(item.ticketCount) || item.ticketCount < 1) {
        return new Response(JSON.stringify({ error: "Invalid ticket count" }), {
          headers: { ...corsHeaders, "Content-Type": "application/json" }, status: 400
        });
      }
      // Validate spaceIds for player board items
      if (item.competitionType === 'player_board') {
        if (!Array.isArray(item.spaceIds) || item.spaceIds.length === 0) {
          return new Response(JSON.stringify({ error: "Player board items require space selection" }), {
            headers: { ...corsHeaders, "Content-Type": "application/json" }, status: 400
          });
        }
        for (const sid of item.spaceIds) {
          if (!UUID_REGEX.test(sid)) {
            return new Response(JSON.stringify({ error: "Invalid space ID" }), {
              headers: { ...corsHeaders, "Content-Type": "application/json" }, status: 400
            });
          }
        }
      }
      validatedItems.push({
        competitionId: item.competitionId,
        ticketCount: item.ticketCount,
        competitionType: item.competitionType,
        spaceIds: item.spaceIds,
      });
    }

    // Fetch competitions
    const compIds = validatedItems.map(i => i.competitionId);
    log("Fetching competitions");
    const { data: competitions, error: compError } = await withTimeout(
      supabaseAdmin
        .from("competitions")
        .select("id, title, ticket_price, sale_price, image_url, status, max_entries, entries_sold, is_free, buy_quantity, free_quantity, multiplier_min_tickets, multiplier_value, exclude_from_discounts, competition_type, is_instant_win, draw_date, draws_on_sellout, bundle_tiers")
        .in("id", compIds),
      DB_TIMEOUT,
      "fetch competitions"
    );

    if (compError || !competitions) {
      log("Failed to fetch competitions", { error: compError?.message });
      return new Response(JSON.stringify({ error: "Failed to fetch competitions" }), {
        headers: { ...corsHeaders, "Content-Type": "application/json" }, status: 500
      });
    }

    const compMap = new Map(competitions.map(c => [c.id, c]));

    // Tickets already held by other customers' in-flight (unpaid) checkouts.
    // Prevents overselling when two people check out for the last tickets at once.
    const pendingHolds = new Map<string, number>();
    try {
      const holdCutoff = new Date(Date.now() - 30 * 60 * 1000).toISOString();
      const { data: pendingOrders } = await withTimeout(
        supabaseAdmin
          .from("orders")
          .select("metadata")
          .eq("status", "pending_payment")
          .gte("created_at", holdCutoff)
          .overlaps("competition_ids", compIds),
        DB_TIMEOUT,
        "fetch pending holds"
      );
      for (const o of pendingOrders ?? []) {
        for (const it of ((o as any)?.metadata?.items ?? [])) {
          if (!compIds.includes(it.competitionId)) continue;
          const held = Number(it.totalTickets ?? it.ticketCount) || 0;
          pendingHolds.set(it.competitionId, (pendingHolds.get(it.competitionId) ?? 0) + held);
        }
      }
    } catch (e) {
      log("Pending hold check skipped", { error: String((e as any)?.message ?? e) });
    }

    // Validate competitions
    for (const item of validatedItems) {
      const comp = compMap.get(item.competitionId);
      if (!comp) {
        return new Response(JSON.stringify({ error: `Competition not found` }), {
          headers: { ...corsHeaders, "Content-Type": "application/json" }, status: 404
        });
      }
      if (comp.status !== 'active') {
        return new Response(JSON.stringify({ error: `Competition "${comp.title}" is not active` }), {
          headers: { ...corsHeaders, "Content-Type": "application/json" }, status: 400
        });
      }
      // Block purchases after draw_date has passed
      if (!comp.draws_on_sellout && comp.draw_date && new Date(comp.draw_date) <= new Date()) {
        log("Competition past draw date", { id: comp.id, draw_date: comp.draw_date });
        return new Response(JSON.stringify({ error: `Competition "${comp.title}" has ended and is no longer accepting entries` }), {
          headers: { ...corsHeaders, "Content-Type": "application/json" }, status: 400
        });
      }

      // Always count bonus/multiplier tickets, plus tickets held by pending checkouts
      const bonusTickets = calcBonusTickets(comp, item.ticketCount);
      const totalTicketsNeeded = item.ticketCount + bonusTickets;
      const held = pendingHolds.get(comp.id) ?? 0;
      const remaining = comp.max_entries - comp.entries_sold - held;
      if (totalTicketsNeeded > remaining) {
        log("Blocked: not enough tickets", {
          id: comp.id, needed: totalTicketsNeeded, remaining, held,
        });
        return new Response(JSON.stringify({
          error: remaining <= 0
            ? `"${comp.title}" has sold out`
            : `Only ${remaining} ticket${remaining === 1 ? '' : 's'} left for "${comp.title}" — you're trying to buy ${totalTicketsNeeded}${bonusTickets > 0 ? ` (including ${bonusTickets} bonus)` : ''}. Please reduce your quantity.`
        }), {
          headers: { ...corsHeaders, "Content-Type": "application/json" }, status: 400
        });
      }
    }

    // Handle player board space reservations
    const playerBoardItems = validatedItems.filter(item => item.competitionType === 'player_board' && item.spaceIds);
    const reservedSpaceIds: string[] = [];

    if (playerBoardItems.length > 0) {
      // Expire old reservations first
      await supabaseAdmin.rpc('expire_space_reservations');

      for (const pbItem of playerBoardItems) {
        // Validate spaces exist and are available
        const { data: spaces, error: spacesError } = await supabaseAdmin
          .from("competition_spaces")
          .select("*")
          .eq("competition_id", pbItem.competitionId)
          .in("id", pbItem.spaceIds!);

        if (spacesError || !spaces || spaces.length !== pbItem.spaceIds!.length) {
          return new Response(JSON.stringify({ error: "Some player board spaces not found" }), {
            headers: { ...corsHeaders, "Content-Type": "application/json" }, status: 400
          });
        }

        const unavailable = spaces.filter((s: any) => s.status !== 'available');
        if (unavailable.length > 0) {
          return new Response(JSON.stringify({
            error: "Some spaces are no longer available",
            unavailableSpaces: unavailable.map((s: any) => ({ number: s.space_number, status: s.status }))
          }), { headers: { ...corsHeaders, "Content-Type": "application/json" }, status: 409 });
        }

        // Reserve spaces
        const now = new Date();
        const reservedUntil = new Date(now.getTime() + RESERVATION_MINUTES * 60 * 1000);

        const { error: reserveError } = await supabaseAdmin
          .from("competition_spaces")
          .update({
            status: "reserved",
            user_id: user.id,
            reserved_at: now.toISOString(),
            reserved_until: reservedUntil.toISOString(),
            updated_at: now.toISOString()
          })
          .in("id", pbItem.spaceIds!)
          .eq("status", "available");

        if (reserveError) {
          log("Failed to reserve spaces", { error: reserveError.message });
          return new Response(JSON.stringify({ error: "Failed to reserve spaces - they may have been taken" }), {
            headers: { ...corsHeaders, "Content-Type": "application/json" }, status: 409
          });
        }

        reservedSpaceIds.push(...pbItem.spaceIds!);
        log("Spaces reserved", { competitionId: pbItem.competitionId, count: pbItem.spaceIds!.length });
      }
    }

    // For free items, create entries immediately (they're confirmed instantly)
    const freeItems = validatedItems.filter(item => compMap.get(item.competitionId)!.is_free);
    const paidItems = validatedItems.filter(item => !compMap.get(item.competitionId)!.is_free);

    const freeEntryIds: string[] = [];
    if (freeItems.length > 0) {
      const freeInserts = freeItems.map(item => ({
        user_id: user.id,
        competition_id: item.competitionId,
        ticket_count: item.ticketCount, // free competitions don't have bundle deals
        amount_paid: 0,
        payment_status: 'completed'
      }));

      const { data: freeEntries, error: freeError } = await supabaseAdmin
        .from("entries")
        .insert(freeInserts)
        .select("id, competition_id");

      if (freeError || !freeEntries) {
        log("Failed to create free entries", { error: freeError?.message });
        return new Response(JSON.stringify({ error: "Failed to create entries" }), {
          headers: { ...corsHeaders, "Content-Type": "application/json" }, status: 500
        });
      }
      freeEntryIds.push(...freeEntries.map(e => e.id));

      // Process instant win for free entries
      for (const entry of freeEntries) {
        const comp = compMap.get(entry.competition_id);
        if (comp?.is_instant_win) {
          const item = freeItems.find(i => i.competitionId === entry.competition_id);
          const ticketCount = item?.ticketCount || 1;
          try {
            for (let t = 0; t < ticketCount; t++) {
              const { data: iwResult, error: iwError } = await supabaseAdmin.rpc('process_instant_win', {
                p_entry_id: entry.id,
                p_competition_id: entry.competition_id,
                p_user_id: user.id,
              });
              if (iwError) log("Free instant win error", { error: iwError.message });
              else log("Free instant win result", { result: iwResult });
            }
          } catch (iwErr) {
            log("Free instant win exception", { error: String(iwErr) });
          }
        }
      }
    }

    // Check if all free
    if (paidItems.length === 0) {
      return new Response(JSON.stringify({
        success: true, message: "All free entries confirmed",
        entryIds: freeEntryIds
      }), { headers: { ...corsHeaders, "Content-Type": "application/json" } });
    }

    // Resolve effective item price: bundle tier price (if selected & valid) overrides unit*count
    const resolveItemTotal = (item: any) => {
      const comp = compMap.get(item.competitionId)!;
      const tiers = Array.isArray((comp as any).bundle_tiers) ? (comp as any).bundle_tiers : [];
      if (item.bundleTierIndex != null && tiers[item.bundleTierIndex]) {
        const tier = tiers[item.bundleTierIndex];
        const tc = Number(tier.ticket_count) || 0;
        const tp = Number(tier.price) || 0;
        if (tc > 0 && tp >= 0 && tc === item.ticketCount) {
          return { total: tp, isBundle: true, tier };
        }
      }
      const effectivePrice = (comp.sale_price != null && Number(comp.sale_price) < Number(comp.ticket_price))
        ? Number(comp.sale_price)
        : Number(comp.ticket_price);
      return { total: effectivePrice * item.ticketCount, isBundle: false, tier: null as any };
    };

    // Calculate total for paid items
    let totalAmount = 0;
    for (const item of paidItems) {
      totalAmount += resolveItemTotal(item).total;
    }

    // Apply promo code discount
    let appliedPromo: any = null;
    if (promoCode && typeof promoCode === 'string') {
      const { data: promo, error: promoError } = await supabaseAdmin
        .from("promo_codes")
        .select("*")
        .eq("code", promoCode.trim().toUpperCase())
        .eq("is_active", true)
        .maybeSingle();

      if (promoError || !promo) {
        return new Response(JSON.stringify({ error: "Invalid promo code" }), {
          headers: { ...corsHeaders, "Content-Type": "application/json" }, status: 400
        });
      }

      if (promo.max_uses && promo.times_used >= promo.max_uses) {
        return new Response(JSON.stringify({ error: "Promo code has expired" }), {
          headers: { ...corsHeaders, "Content-Type": "application/json" }, status: 400
        });
      }

      if (promo.valid_until && new Date(promo.valid_until) < new Date()) {
        return new Response(JSON.stringify({ error: "Promo code has expired" }), {
          headers: { ...corsHeaders, "Content-Type": "application/json" }, status: 400
        });
      }

      if (promo.min_order_amount && totalAmount < promo.min_order_amount) {
        return new Response(JSON.stringify({ error: `Minimum order amount is £${promo.min_order_amount}` }), {
          headers: { ...corsHeaders, "Content-Type": "application/json" }, status: 400
        });
      }

      // Build set of excluded competition IDs (promo-level + competition-level)
      const excludedIds = new Set<string>(promo.excluded_competition_ids || []);
      for (const comp of competitions!) {
        if (comp.exclude_from_discounts) {
          excludedIds.add(comp.id);
        }
      }

      // Split amount into discountable vs non-discountable
      let discountableAmount = 0;
      let nonDiscountableAmount = 0;
      for (const item of paidItems) {
        const itemTotal = resolveItemTotal(item).total;
        if (excludedIds.has(item.competitionId)) {
          nonDiscountableAmount += itemTotal;
        } else {
          discountableAmount += itemTotal;
        }
      }

      // Apply discount only to discountable amount
      if (promo.discount_type === 'percentage') {
        discountableAmount = discountableAmount * (1 - promo.discount_value / 100);
      } else {
        discountableAmount = Math.max(0, discountableAmount - promo.discount_value);
      }
      totalAmount = Math.round((discountableAmount + nonDiscountableAmount) * 100) / 100;

      appliedPromo = promo;
      log("Promo code applied", { code: promo.code, discount: promo.discount_value, type: promo.discount_type, excludedComps: [...excludedIds] });

      // Atomically increment usage (race-safe; respects max_uses under concurrency)
      const { data: consumeResult, error: consumeError } = await supabaseAdmin
        .rpc("consume_promo_code", { p_code: promo.code });
      if (consumeError || !consumeResult?.success) {
        return new Response(JSON.stringify({
          error: consumeResult?.error || "Promo code could not be applied"
        }), {
          headers: { ...corsHeaders, "Content-Type": "application/json" }, status: 400
        });
      }
    }

    // Apply site credit
    let creditUsed = 0;
    if (creditAmount && typeof creditAmount === 'number' && creditAmount > 0) {
      // Verify user has enough credit
      const { data: balanceData, error: balanceError } = await supabaseAdmin.rpc('get_user_credit_balance', {
        p_user_id: user.id,
      });
      if (balanceError) {
        log("Failed to check credit balance", { error: balanceError.message });
        return new Response(JSON.stringify({ error: "Failed to verify credit balance" }), {
          headers: { ...corsHeaders, "Content-Type": "application/json" }, status: 500
        });
      }
      const availableCredit = Number(balanceData) || 0;
      creditUsed = Math.min(creditAmount, availableCredit, totalAmount);
      creditUsed = Math.round(creditUsed * 100) / 100;
      
      if (creditUsed > 0) {
        totalAmount = Math.round((totalAmount - creditUsed) * 100) / 100;
        log("Credit applied", { creditUsed, remainingTotal: totalAmount });
      }
    }

    // Responsible Play: check user spend limits / cool-off / self-exclusion
    if (totalAmount > 0) {
      const { data: limitCheck, error: limitErr } = await supabaseAdmin.rpc('check_user_spend_limit', {
        p_user_id: user.id,
        p_amount: totalAmount,
      });
      if (limitErr) {
        log("Limit check failed", { error: limitErr.message });
      } else if (limitCheck && (limitCheck as any).allowed === false) {
        const lc: any = limitCheck;
        log("Purchase blocked by user limit", lc);
        // Release any reserved spaces
        if (reservedSpaceIds.length > 0) {
          await supabaseAdmin.from("competition_spaces")
            .update({ status: "available", user_id: null, order_id: null, reserved_at: null, reserved_until: null, updated_at: new Date().toISOString() })
            .in("id", reservedSpaceIds);
        }
        return new Response(JSON.stringify({
          error: lc.message || 'Purchase blocked by your Responsible Play settings.',
          responsiblePlay: true,
          reason: lc.reason,
        }), { headers: { ...corsHeaders, "Content-Type": "application/json" }, status: 403 });
      }
    }

    // Look up influencer linked to the promo code

    let influencerId: string | null = null;
    if (appliedPromo) {
      const { data: influencer } = await supabaseAdmin
        .from("influencers")
        .select("id")
        .eq("promo_code_id", appliedPromo.id)
        .eq("is_active", true)
        .maybeSingle();
      
      if (influencer) {
        influencerId = influencer.id;
        log("Influencer linked", { influencerId, promoCode: appliedPromo.code });
      }
    }

    const orderNumber = generateOrderNumber();
    const competitionIds = paidItems.map(i => i.competitionId);

    // Build items with bundle deal info for metadata
    const itemsWithBundles = paidItems.map(item => {
      const comp = compMap.get(item.competitionId)!;
      const isPlayerBoard = item.competitionType === 'player_board';
      const resolved = resolveItemTotal(item);
      const bonusTickets = (!isPlayerBoard && !resolved.isBundle)
        ? calcBonusTickets(comp, item.ticketCount)
        : 0;
      return {
        competitionId: item.competitionId,
        ticketCount: item.ticketCount,
        bonusTickets,
        totalTickets: item.ticketCount + bonusTickets,
        ...(resolved.isBundle ? { bundleTierIndex: item.bundleTierIndex, bundlePrice: resolved.total } : {}),
        ...(comp.is_instant_win ? { isInstantWin: true } : {}),
        ...(isPlayerBoard && item.spaceIds ? { competitionType: 'player_board', spaceIds: item.spaceIds } : {}),
      };
    });

    // Build player board metadata for the webhook
    const playerBoardMeta: any = {};
    for (const pbItem of playerBoardItems) {
      playerBoardMeta[pbItem.competitionId] = {
        type: 'player_board',
        spaceIds: pbItem.spaceIds,
      };
    }

    // Create order - entries are NOT created yet, only on successful payment
    const hasPlayerBoard = playerBoardItems.length > 0;
    const orderMetadata: any = {
      items: itemsWithBundles,
      userEmail: user.email,
      promoCode: appliedPromo?.code || null,
      creditUsed: creditUsed || 0,
    };
    // If there are player board items, add their info for the webhook
    if (hasPlayerBoard) {
      // Collect all player board space IDs and competition IDs
      const allPbSpaceIds: string[] = [];
      const pbCompIds: string[] = [];
      for (const pbItem of playerBoardItems) {
        allPbSpaceIds.push(...(pbItem.spaceIds || []));
        pbCompIds.push(pbItem.competitionId);
      }
      orderMetadata.type = 'player_board';
      orderMetadata.spaceIds = allPbSpaceIds;
      orderMetadata.competitionId = pbCompIds[0]; // Primary player board competition
      orderMetadata.playerBoardCompetitions = playerBoardMeta;
    }

    log("Creating order", { orderNumber, amount: totalAmount });
    const { data: order, error: orderError } = await withTimeout(
      supabaseAdmin
        .from("orders")
        .insert({
          order_number: orderNumber,
          user_id: user.id,
          amount: totalAmount,
          currency: "GBP",
          status: "pending_payment",
          entry_ids: [],
          competition_ids: competitionIds,
          promo_code: appliedPromo?.code || null,
          influencer_id: influencerId,
          metadata: orderMetadata,
        })
        .select("id")
        .single(),
      DB_TIMEOUT,
      "create order"
    );

    if (orderError || !order) {
      // Release any reserved spaces on failure
      if (reservedSpaceIds.length > 0) {
        await supabaseAdmin.from("competition_spaces")
          .update({ status: "available", user_id: null, order_id: null, reserved_at: null, reserved_until: null, updated_at: new Date().toISOString() })
          .in("id", reservedSpaceIds);
      }
      log("Failed to create order", { error: orderError?.message });
      return new Response(JSON.stringify({ error: orderError?.code === '23514' ? orderError.message : "Failed to create order" }), {
        headers: { ...corsHeaders, "Content-Type": "application/json" }, status: orderError?.code === '23514' ? 400 : 500
      });
    }

    // Link reserved spaces to this order
    if (reservedSpaceIds.length > 0) {
      await supabaseAdmin.from("competition_spaces").update({ order_id: order.id }).in("id", reservedSpaceIds);
    }

    log("Order created", { orderNumber, amount: totalAmount, creditUsed });

    // If fully covered by credit, create entries immediately
    if (totalAmount <= 0 && creditUsed > 0) {
      // Separate player board items from standard items for credit-only flow
      const creditPbItems = itemsWithBundles.filter((item: any) => item.competitionType === 'player_board');
      const creditStdItems = itemsWithBundles.filter((item: any) => item.competitionType !== 'player_board');

      // Create standard entries
      const paidInserts = creditStdItems.map(item => ({
        user_id: user.id,
        competition_id: item.competitionId,
        ticket_count: item.totalTickets,
        amount_paid: 0,
        payment_status: 'completed'
      }));

      let entryIds: string[] = [];
      let paidEntries: any[] = [];

      if (paidInserts.length > 0) {
        const { data, error: paidEntryError } = await supabaseAdmin
          .from("entries")
          .insert(paidInserts)
          .select("id, competition_id");

        if (paidEntryError) {
          log("Failed to create credit entries", { error: paidEntryError.message });
          return new Response(JSON.stringify({ error: "Failed to create entries" }), {
            headers: { ...corsHeaders, "Content-Type": "application/json" }, status: 500
          });
        }
        paidEntries = data || [];
        entryIds = paidEntries.map(e => e.id);
      }

      // Handle player board spaces for credit-only orders
      for (const pbItem of creditPbItems) {
        const pbSpaceIds = (pbItem as any).spaceIds || [];
        if (pbSpaceIds.length === 0) continue;

        // Mark spaces as sold
        const { error: spaceUpdateErr } = await supabaseAdmin
          .from("competition_spaces")
          .update({ status: "sold", reserved_at: null, reserved_until: null, updated_at: new Date().toISOString() })
          .in("id", pbSpaceIds)
          .eq("status", "reserved");

        if (spaceUpdateErr) {
          log("Failed to update player board spaces (credit)", { error: spaceUpdateErr.message });
        }

        // Create entries for each space
        const spaceEntries = pbSpaceIds.map((sid: string) => ({
          user_id: user.id,
          competition_id: pbItem.competitionId,
          ticket_count: 1,
          amount_paid: 0,
          payment_status: "completed",
        }));

        const { data: pbEntries, error: pbEntryErr } = await supabaseAdmin
          .from("entries")
          .insert(spaceEntries)
          .select("id");

        if (pbEntryErr) {
          log("Failed to create player board credit entries", { error: pbEntryErr.message });
        } else if (pbEntries) {
          // Link entries to spaces
          for (let i = 0; i < pbSpaceIds.length && i < pbEntries.length; i++) {
            await supabaseAdmin.from("competition_spaces")
              .update({ entry_id: pbEntries[i].id, order_id: order.id })
              .eq("id", pbSpaceIds[i]);
          }
          entryIds.push(...pbEntries.map((e: any) => e.id));

          // Check if all spaces sold → auto-draw
          const { count: totalSpaces } = await supabaseAdmin.from("competition_spaces")
            .select("id", { count: "exact" }).eq("competition_id", pbItem.competitionId);
          const { count: soldSpaces } = await supabaseAdmin.from("competition_spaces")
            .select("id", { count: "exact" }).eq("competition_id", pbItem.competitionId).in("status", ["sold", "winner"]);

          if (soldSpaces === totalSpaces && totalSpaces && totalSpaces > 0) {
            log("All spaces sold (credit) - triggering auto-draw");
            const { data: drawResult } = await supabaseAdmin.rpc('player_board_auto_draw', { p_competition_id: pbItem.competitionId });
            log("Auto-draw result (credit)", drawResult);
          }
        }
      }

      // Deduct site credit (atomic to prevent over-spending)
      const { data: deductedAmount, error: deductErr } = await supabaseAdmin.rpc('deduct_site_credit', {
        p_user_id: user.id,
        p_amount: creditUsed,
        p_reason: `Order ${orderNumber}`,
        p_order_id: order.id,
      });
      if (deductErr) {
        log("Credit deduction error", { error: deductErr.message });
      } else {
        log("Credit deducted", { requested: creditUsed, actual: deductedAmount });
      }

      // Mark order as paid
      await supabaseAdmin.from("orders").update({
        status: "paid",
        entry_ids: [...freeEntryIds, ...entryIds],
      }).eq("id", order.id);

      // Process instant win for credit-covered entries
      const creditInstantWinPrizes: string[] = [];
      for (const item of creditStdItems) {
        const comp = compMap.get(item.competitionId);
        if (comp?.is_instant_win) {
          const entry = paidEntries.find((e: any) => e.competition_id === item.competitionId);
          if (entry) {
            try {
              for (let t = 0; t < item.totalTickets; t++) {
                const { data: iwResult, error: iwError } = await supabaseAdmin.rpc('process_instant_win', {
                  p_entry_id: entry.id,
                  p_competition_id: item.competitionId,
                  p_user_id: user.id,
                });
                if (iwError) {
                  log("Credit instant win error", { entryId: entry.id, ticket: t, error: iwError.message });
                } else {
                  log("Credit instant win result", { entryId: entry.id, ticket: t, result: iwResult });
                  const r = iwResult as any;
                  if (r?.is_winner && r?.prize_name && r.prize_name.toLowerCase() !== 'no prize' && (r.prize_value ?? 0) > 0) {
                    creditInstantWinPrizes.push(r.prize_name);
                  }
                }
              }
            } catch (iwErr) {
              log("Credit instant win exception", { error: String(iwErr) });
            }
          }
        }
      }

      log("Order fully covered by credit", { orderNumber, creditUsed, entries: entryIds.length, instantWinPrizes: creditInstantWinPrizes });

      return new Response(JSON.stringify({
        success: true,
        message: `Order paid with £${creditUsed.toFixed(2)} site credit`,
        orderNumber: order.order_number,
        entryIds: [...freeEntryIds, ...entryIds],
        instantWinPrizes: creditInstantWinPrizes.length > 0 ? creditInstantWinPrizes : undefined,
      }), { headers: { ...corsHeaders, "Content-Type": "application/json" } });
    }

    // Get user profile for name
    const { data: profile } = await supabaseAdmin
      .from("profiles")
      .select("full_name")
      .eq("user_id", user.id)
      .single();

    const names = (profile?.full_name || "").split(" ");
    const firstName = names[0] || "";
    const lastName = names.slice(1).join(" ") || "";

    // Build Cashflows payload
    const cashflowsPayload = {
      type: "Payment",
      paymentMethodsToUse: ["creditcard"],
      amountToCollect: totalAmount.toFixed(2),
      currency: "GBP",
      locale: "en_GB",
      order: {
        orderNumber: orderNumber,
        billingAddress: {
          firstName: firstName,
          lastName: lastName,
        },
        billingIdentity: {
          emailAddress: user.email,
        },
      },
      parameters: {
        returnUrlSuccess: `${siteBaseUrl}/payment/success?order=${orderNumber}`,
        returnUrlFailed: `${siteBaseUrl}/payment/failed?order=${orderNumber}`,
        returnUrlCancelled: `${siteBaseUrl}/payment/cancelled?order=${orderNumber}`,
      }
    };

    const minifiedBody = JSON.stringify(cashflowsPayload);
    const hash = await createHash(apiKey, minifiedBody);
    const baseUrl = getBaseUrl();

    log("Calling Cashflows", { baseUrl, orderNumber, configIdPrefix: configId?.substring(0, 6), configIdLength: configId?.length });

    const cfResponse = await withTimeout(
      fetch(`${baseUrl}/api/gateway/payment-jobs`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "ConfigurationId": configId,
          "Hash": hash,
        },
        body: minifiedBody,
      }),
      API_TIMEOUT,
      "Cashflows API call"
    );

    const cfData = await cfResponse.json();
    log("Cashflows response", { status: cfResponse.status, data: cfData });

    if (!cfResponse.ok) {
      // Clean up - mark order failed
      await supabaseAdmin.from("orders").update({ status: "failed", cashflows_last_status: `HTTP ${cfResponse.status}` }).eq("id", order.id);
      return new Response(JSON.stringify({ error: "Payment provider error", details: cfData }), {
        headers: { ...corsHeaders, "Content-Type": "application/json" }, status: 502
      });
    }

    // Store references - Cashflows wraps response in a "data" object
    const cfInner = cfData.data || cfData;
    const paymentJobRef = cfInner.reference || cfInner.paymentJobReference || cfInner.id;
    const actionUrl = cfInner?.links?.action?.url || cfData?.links?.action?.url || cfInner.actionUrl || cfInner.url;

    await supabaseAdmin.from("orders").update({
      cashflows_payment_job_reference: paymentJobRef,
      cashflows_action_url: actionUrl,
    }).eq("id", order.id);

    log("Checkout created", { paymentJobRef, actionUrl });

    return new Response(JSON.stringify({
      url: actionUrl,
      orderNumber: orderNumber,
      paymentJobReference: paymentJobRef,
    }), {
      headers: { ...corsHeaders, "Content-Type": "application/json" },
    });

  } catch (error) {
    const msg = error instanceof Error ? error.message : String(error);
    const isTimeout = msg.includes("Timeout after");
    log("ERROR", { message: msg, isTimeout });
    
    const userMessage = isTimeout
      ? "The payment system is temporarily busy. Please try again in a moment."
      : "An error occurred processing your payment. Please try again.";
    
    return new Response(JSON.stringify({ error: userMessage }), {
      headers: { ...corsHeaders, "Content-Type": "application/json" }, 
      status: isTimeout ? 504 : 500
    });
  }
});

````


---

## delete-user

- **Purpose / logic:** Deletes an auth user (account deletion).
- **Trigger & auth:** Authenticated request; caller must be the user themselves or admin (403 otherwise).
- **Inputs:** { userId }
- **Outputs / external calls:** Deletes auth user via admin API.

### `supabase/functions/delete-user/index.ts`

````ts
import { createClient } from "https://esm.sh/@supabase/supabase-js@2.89.0";

const corsHeaders = {
  "Access-Control-Allow-Origin": "*",
  "Access-Control-Allow-Headers":
    "authorization, x-client-info, apikey, content-type",
};

Deno.serve(async (req) => {
  if (req.method === "OPTIONS") {
    return new Response("ok", { headers: corsHeaders });
  }

  try {
    // Verify the caller is an admin
    const authHeader = req.headers.get("Authorization");
    if (!authHeader) {
      return new Response(JSON.stringify({ error: "Unauthorized" }), {
        status: 401,
        headers: { ...corsHeaders, "Content-Type": "application/json" },
      });
    }

    const supabaseUrl = Deno.env.get("SUPABASE_URL")!;
    const supabaseServiceKey = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!;
    const anonKey = Deno.env.get("SUPABASE_ANON_KEY")!;

    // Verify caller is admin using their JWT
    const callerClient = createClient(supabaseUrl, anonKey, {
      global: { headers: { Authorization: authHeader } },
    });
    const { data: { user: caller } } = await callerClient.auth.getUser();
    if (!caller) {
      return new Response(JSON.stringify({ error: "Invalid token" }), {
        status: 401,
        headers: { ...corsHeaders, "Content-Type": "application/json" },
      });
    }

    // Check admin role
    const adminClient = createClient(supabaseUrl, supabaseServiceKey);
    const { data: roleData } = await adminClient
      .from("user_roles")
      .select("role")
      .eq("user_id", caller.id)
      .eq("role", "admin")
      .maybeSingle();

    if (!roleData) {
      return new Response(JSON.stringify({ error: "Admin access required" }), {
        status: 403,
        headers: { ...corsHeaders, "Content-Type": "application/json" },
      });
    }

    const { user_id } = await req.json();
    if (!user_id || typeof user_id !== "string") {
      return new Response(JSON.stringify({ error: "user_id is required" }), {
        status: 400,
        headers: { ...corsHeaders, "Content-Type": "application/json" },
      });
    }

    // Don't allow deleting yourself
    if (user_id === caller.id) {
      return new Response(JSON.stringify({ error: "Cannot delete your own account" }), {
        status: 400,
        headers: { ...corsHeaders, "Content-Type": "application/json" },
      });
    }

    // Delete the user from auth (cascades to profiles, user_roles, etc.)
    const { error } = await adminClient.auth.admin.deleteUser(user_id);
    if (error) {
      console.error("Error deleting user:", error);
      return new Response(JSON.stringify({ error: error.message }), {
        status: 500,
        headers: { ...corsHeaders, "Content-Type": "application/json" },
      });
    }

    return new Response(JSON.stringify({ success: true }), {
      status: 200,
      headers: { ...corsHeaders, "Content-Type": "application/json" },
    });
  } catch (err) {
    console.error("Unexpected error:", err);
    return new Response(JSON.stringify({ error: "Internal server error" }), {
      status: 500,
      headers: { ...corsHeaders, "Content-Type": "application/json" },
    });
  }
});

````


---

## draw-winner

- **Purpose / logic:** Admin live draw. Builds the full ticket pool (paid entries + postal entries, sequential or entry_tickets random), selects winning ticket with crypto.getRandomValues (or confirms an admin-supplied ticket), writes winners + draw_audits (seed, hash), sends winner email.
- **Trigger & auth:** Admin UI (AdminLiveDraw). verify_jwt=false; validates JWT + has_role admin.
- **Inputs:** { competitionId, action?, ticketNumber?, videoTimestamp? }
- **Outputs / external calls:** Winner details JSON; DB writes; send-email.

### `supabase/functions/draw-winner/index.ts`

````ts
import { createClient } from "npm:@supabase/supabase-js@2.49.4";

const corsHeaders = {
  'Access-Control-Allow-Origin': '*',
  'Access-Control-Allow-Headers': 'authorization, x-client-info, apikey, content-type, x-supabase-client-platform, x-supabase-client-platform-version, x-supabase-client-runtime, x-supabase-client-runtime-version',
};

interface EntryTicket {
  id: string;
  userId: string | null;
  name: string;
  email: string;
  type: 'paid' | 'postal';
  ticketNumber: number;
}

Deno.serve(async (req) => {
  if (req.method === 'OPTIONS') {
    return new Response(null, { headers: corsHeaders });
  }

  try {
    const authHeader = req.headers.get('Authorization');
    if (!authHeader) {
      return new Response(
        JSON.stringify({ error: 'Unauthorized' }),
        { status: 401, headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
      );
    }

    const supabaseClient = createClient(
      Deno.env.get('SUPABASE_URL') ?? '',
      Deno.env.get('SUPABASE_ANON_KEY') ?? '',
      { global: { headers: { Authorization: authHeader } } }
    );

    const { data: { user }, error: authError } = await supabaseClient.auth.getUser();
    if (authError || !user) {
      return new Response(
        JSON.stringify({ error: 'Unauthorized' }),
        { status: 401, headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
      );
    }

    const { data: isAdmin, error: roleError } = await supabaseClient.rpc('has_role', {
      _user_id: user.id,
      _role: 'admin'
    });

    if (roleError || !isAdmin) {
      return new Response(
        JSON.stringify({ error: 'Forbidden: Admin access required' }),
        { status: 403, headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
      );
    }

    const { competitionId, winningTicketNumber, action, randomSeed } = await req.json();

    if (!competitionId || typeof competitionId !== 'string') {
      return new Response(
        JSON.stringify({ error: 'Invalid competition ID' }),
        { status: 400, headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
      );
    }

    const supabaseAdmin = createClient(
      Deno.env.get('SUPABASE_URL') ?? '',
      Deno.env.get('SUPABASE_SERVICE_ROLE_KEY') ?? ''
    );

    // Check competition
    const { data: competition, error: compError } = await supabaseAdmin
      .from('competitions')
      .select('id, title, status, random_ticket_allocation, max_entries')
      .eq('id', competitionId)
      .single();

    if (compError || !competition) {
      return new Response(
        JSON.stringify({ error: 'Competition not found' }),
        { status: 404, headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
      );
    }

    // Build ticket pool using ACTUAL ticket numbers from the database
    const tickets: EntryTicket[] = [];
    let maxPaidTicket = 0;
    let paidTicketCount = 0;
    let postalTicketCount = 0;

    if (competition.random_ticket_allocation) {
      // Randomly allocated competitions: every ticket number is stored individually
      const rows: any[] = [];
      const pageSize = 1000;
      let from = 0;
      while (true) {
        const { data, error } = await supabaseAdmin
          .from('entry_tickets')
          .select('ticket_number, user_id, entry_id, postal_entry_id')
          .eq('competition_id', competitionId)
          .order('ticket_number', { ascending: true })
          .range(from, from + pageSize - 1);

        if (error) {
          return new Response(
            JSON.stringify({ error: 'Failed to fetch allocated tickets' }),
            { status: 500, headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
          );
        }
        rows.push(...(data ?? []));
        if (!data || data.length < pageSize) break;
        from += pageSize;
      }

      const userIds = Array.from(new Set(rows.map((r) => r.user_id).filter(Boolean)));
      const profilesById: Record<string, { full_name?: string; email?: string }> = {};
      if (userIds.length) {
        const { data: profiles } = await supabaseAdmin
          .from('profiles')
          .select('user_id, full_name, email')
          .in('user_id', userIds);
        for (const p of profiles ?? []) {
          profilesById[p.user_id] = { full_name: p.full_name, email: p.email };
        }
      }

      const postalById: Record<string, { full_name: string; email: string }> = {};
      const postalIds = Array.from(new Set(rows.map((r) => r.postal_entry_id).filter(Boolean)));
      if (postalIds.length) {
        const { data: postals } = await supabaseAdmin
          .from('postal_entries')
          .select('id, full_name, email')
          .in('id', postalIds);
        for (const p of postals ?? []) {
          postalById[p.id] = { full_name: p.full_name, email: p.email };
        }
      }

      for (const row of rows) {
        const isPostal = !!row.postal_entry_id;
        const prof = row.user_id ? profilesById[row.user_id] : undefined;
        const postal = isPostal ? postalById[row.postal_entry_id] : undefined;
        tickets.push({
          id: row.entry_id ?? row.postal_entry_id,
          userId: row.user_id ?? null,
          name: (isPostal ? postal?.full_name : prof?.full_name) || 'Unknown',
          email: (isPostal ? postal?.email : prof?.email) || '',
          type: isPostal ? 'postal' : 'paid',
          ticketNumber: row.ticket_number,
        });
        if (isPostal) postalTicketCount++; else paidTicketCount++;
        if (row.ticket_number > maxPaidTicket) maxPaidTicket = row.ticket_number;
      }
    } else {
      // Sequential competitions: derive numbers from each entry's start number
      const { data: paidEntries, error: paidError } = await supabaseAdmin
        .from('entries')
        .select('id, user_id, ticket_count, ticket_start_number')
        .eq('competition_id', competitionId)
        .eq('payment_status', 'completed')
        .not('ticket_start_number', 'is', null)
        .order('ticket_start_number', { ascending: true });

      if (paidError) {
        return new Response(
          JSON.stringify({ error: 'Failed to fetch paid entries' }),
          { status: 500, headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
        );
      }

      for (const entry of paidEntries || []) {
        const { data: profile } = await supabaseAdmin
          .from('profiles')
          .select('full_name, email')
          .eq('user_id', entry.user_id)
          .single();

        for (let i = 0; i < entry.ticket_count; i++) {
          const realTicketNumber = entry.ticket_start_number + i;
          tickets.push({
            id: entry.id,
            userId: entry.user_id,
            name: profile?.full_name || 'Unknown',
            email: profile?.email || '',
            type: 'paid',
            ticketNumber: realTicketNumber
          });
          if (realTicketNumber > maxPaidTicket) {
            maxPaidTicket = realTicketNumber;
          }
        }
        paidTicketCount += entry.ticket_count;
      }

      // Fetch postal entries — assign ticket numbers sequentially after the last paid ticket
      const { data: postalEntries, error: postalError } = await supabaseAdmin
        .from('postal_entries')
        .select('id, full_name, email, ticket_count, ticket_start_number')
        .eq('competition_id', competitionId)
        .order('created_at', { ascending: true });

      if (postalError) {
        return new Response(
          JSON.stringify({ error: 'Failed to fetch postal entries' }),
          { status: 500, headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
        );
      }

      let postalTicketNumber = Math.max(maxPaidTicket, ...(postalEntries || []).map(e => e.ticket_start_number == null ? 0 : e.ticket_start_number + e.ticket_count - 1)) + 1;

      for (const entry of postalEntries || []) {
        for (let i = 0; i < entry.ticket_count; i++) {
          tickets.push({
            id: entry.id,
            userId: null,
            name: entry.full_name,
            email: entry.email,
            type: 'postal',
            ticketNumber: entry.ticket_start_number == null ? postalTicketNumber++ : entry.ticket_start_number + i
          });
        }
        postalTicketCount += entry.ticket_count;
      }
    }

    const totalTickets = tickets.length;
    // Highest ticket number actually in play (random pools are sparse)
    const maxTicketNumber = tickets.reduce((m, t) => (t.ticketNumber > m ? t.ticketNumber : m), 0);

    if (totalTickets === 0) {
      return new Response(
        JSON.stringify({ error: 'No entries to draw from' }),
        { status: 400, headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
      );
    }

    // ACTION: spin - server-side cryptographic RNG picks a ticket that is genuinely in play
    if (action === 'spin') {
      // Unbiased index in [0, totalTickets) via rejection sampling
      const pickIndex = (n: number): number => {
        const limit = Math.floor(0xFFFFFFFF / n) * n;
        const buf = new Uint32Array(1);
        let v = 0;
        do {
          crypto.getRandomValues(buf);
          v = buf[0];
        } while (v >= limit);
        return v % n;
      };

      const index = pickIndex(totalTickets);
      const ticket = tickets[index];

      const seedBytes = new Uint8Array(16);
      crypto.getRandomValues(seedBytes);
      const seed = Array.from(seedBytes, (b) => b.toString(16).padStart(2, '0')).join('');

      console.log(`Spin for ${competitionId}: index ${index}/${totalTickets} -> ticket #${ticket.ticketNumber}`);

      return new Response(
        JSON.stringify({
          success: true,
          action: 'spin',
          seed: `server-csprng:${seed}`,
          ticket: {
            name: ticket.name,
            email: ticket.email,
            ticketNumber: ticket.ticketNumber,
            type: ticket.type,
          },
          stats: {
            totalTickets,
            paidTickets: paidTicketCount,
            postalTickets: postalTicketCount,
            maxTicketNumber,
          },
        }),
        { status: 200, headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
      );
    }

    // ACTION: lookup - just find who owns a ticket number
    if (action === 'lookup') {
      if (!winningTicketNumber || winningTicketNumber < 1 || winningTicketNumber > maxTicketNumber) {
        return new Response(
          JSON.stringify({ error: `Invalid ticket number. Must be between 1 and ${maxTicketNumber}` }),
          { status: 400, headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
        );
      }

      const ticket = tickets.find(t => t.ticketNumber === winningTicketNumber);
      if (!ticket) {
        return new Response(
          JSON.stringify({ error: 'Ticket not found' }),
          { status: 404, headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
        );
      }

      return new Response(
        JSON.stringify({
          success: true,
          action: 'lookup',
          ticket: {
            name: ticket.name,
            email: ticket.email,
            ticketNumber: ticket.ticketNumber,
            type: ticket.type,
          },
          stats: {
            totalTickets,
            paidTickets: paidTicketCount,
            postalTickets: postalTicketCount,
          }
        }),
        { status: 200, headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
      );
    }

    // ACTION: confirm - record the winner with the given ticket number
    if (action === 'confirm') {
      if (!winningTicketNumber || winningTicketNumber < 1 || winningTicketNumber > maxTicketNumber) {
        return new Response(
          JSON.stringify({ error: `Invalid ticket number. Must be between 1 and ${maxTicketNumber}` }),
          { status: 400, headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
        );
      }

      // Check if winner already exists
      const { data: existingWinner } = await supabaseAdmin
        .from('winners')
        .select('id')
        .eq('competition_id', competitionId)
        .maybeSingle();

      if (existingWinner) {
        return new Response(
          JSON.stringify({ error: 'A winner has already been recorded for this competition' }),
          { status: 400, headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
        );
      }

      const selectedWinner = tickets.find(t => t.ticketNumber === winningTicketNumber);
      if (!selectedWinner) {
        return new Response(
          JSON.stringify({ error: 'Ticket not found' }),
          { status: 404, headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
        );
      }

      // Generate audit hash
      const auditData = `${competitionId}|${winningTicketNumber}|${selectedWinner.id}|${new Date().toISOString()}`;
      const encoder = new TextEncoder();
      const hashBuffer = await crypto.subtle.digest('SHA-256', encoder.encode(auditData));
      const auditHash = Array.from(new Uint8Array(hashBuffer), b => b.toString(16).padStart(2, '0')).join('');

      // Create winner record
      const { data: newWinner, error: winnerError } = await supabaseAdmin
        .from('winners')
        .insert({
          user_id: selectedWinner.userId,
          competition_id: competitionId,
          winner_name: selectedWinner.name,
          prize_description: competition.title,
          announced: false
        })
        .select()
        .single();

      if (winnerError) {
        console.error('Error creating winner:', winnerError);
        return new Response(
          JSON.stringify({ error: 'Failed to create winner record' }),
          { status: 500, headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
        );
      }

      // Create audit record
      const { error: auditError } = await supabaseAdmin
        .from('draw_audits')
        .insert({
          competition_id: competitionId,
          performed_by_admin_id: user.id,
          total_paid_entries: paidTicketCount,
          total_postal_entries: postalTicketCount,
          total_tickets: totalTickets,
          random_seed: typeof randomSeed === 'string' && randomSeed.length > 0 && randomSeed.length <= 128
            ? randomSeed
            : 'manual-rng-live-draw',
          winning_ticket_number: winningTicketNumber,
          winner_type: selectedWinner.type,
          winner_entry_id: selectedWinner.id,
          winner_name: selectedWinner.name,
          winner_email: selectedWinner.email,
          audit_hash: auditHash,
          is_published: false
        });

      if (auditError) {
        console.error('Error creating audit:', auditError);
        await supabaseAdmin.from('winners').delete().eq('id', newWinner.id);
        return new Response(
          JSON.stringify({ error: 'Failed to create audit record' }),
          { status: 500, headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
        );
      }

      // Update competition status
      await supabaseAdmin
        .from('competitions')
        .update({ status: 'ended' })
        .eq('id', competitionId);

      // Send winner notification email
      if (selectedWinner.email) {
        try {
          await fetch(`${Deno.env.get("SUPABASE_URL")}/functions/v1/send-email`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Authorization': `Bearer ${Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")}`
            },
            body: JSON.stringify({
              type: 'winner_notification',
              to: selectedWinner.email,
              data: {
                competitionTitle: competition.title,
                prizeDescription: competition.title,
                winnerName: selectedWinner.name,
                userId: selectedWinner.userId
              }
            })
          });
        } catch (emailErr) {
          console.error('Failed to send winner email:', emailErr);
        }
      }

      console.log(`Winner recorded: ${selectedWinner.name} (ticket #${winningTicketNumber}) for ${competition.title}`);

      return new Response(
        JSON.stringify({
          success: true,
          action: 'confirm',
          winner: {
            id: newWinner.id,
            name: selectedWinner.name,
            email: selectedWinner.email,
            ticketNumber: winningTicketNumber,
            type: selectedWinner.type,
            competitionTitle: competition.title
          },
          audit: {
            totalTickets,
            paidTickets: paidTicketCount,
            postalTickets: postalTicketCount,
            auditHash
          }
        }),
        { status: 200, headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
      );
    }

    return new Response(
      JSON.stringify({ error: 'Invalid action. Use "spin", "lookup" or "confirm".' }),
      { status: 400, headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
    );

  } catch (error) {
    console.error('Unexpected error in draw-winner:', error);
    return new Response(
      JSON.stringify({ error: 'Internal server error' }),
      { status: 500, headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
    );
  }
});

````


---

## expire-stuck-orders

- **Purpose / logic:** Marks pending_payment/created orders older than 2 hours as expired and releases reserved player-board spaces.
- **Trigger & auth:** pg_cron job 6 hourly at :15 with x-cron-secret (CRON_SECRET_KEY or AUTO_DRAW_CRON_TOKEN).
- **Inputs:** none
- **Outputs / external calls:** { expired: n }

### `supabase/functions/expire-stuck-orders/index.ts`

````ts
// Scheduled function: cancels orders stuck in pending_payment > 2h
// and clears any reserved competition_spaces tied to them.
import { createClient } from "npm:@supabase/supabase-js@2.49.4";

const corsHeaders = {
  "Access-Control-Allow-Origin": "*",
  "Access-Control-Allow-Headers": "authorization, x-client-info, apikey, content-type",
};

Deno.serve(async (req) => {
  if (req.method === "OPTIONS") return new Response(null, { headers: corsHeaders });

  // Required shared-secret. Reject if header missing or mismatched.
  const accepted = [
    Deno.env.get("CRON_SECRET_KEY"),
    Deno.env.get("AUTO_DRAW_CRON_TOKEN"),
  ].filter(Boolean) as string[];
  const provided = req.headers.get("x-cron-secret") ??
    (req.headers.get("authorization") ?? "").replace(/^Bearer\s+/i, "");
  if (accepted.length === 0 || !provided || !accepted.includes(provided)) {
    return new Response(JSON.stringify({ error: "unauthorized" }), {
      status: 401,
      headers: { ...corsHeaders, "Content-Type": "application/json" },
    });
  }

  const supabase = createClient(
    Deno.env.get("SUPABASE_URL")!,
    Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!,
  );

  try {
    const cutoff = new Date(Date.now() - 2 * 60 * 60 * 1000).toISOString();

    // Find stuck orders
    const { data: stuck, error: findErr } = await supabase
      .from("orders")
      .select("id, order_number, created_at")
      .in("status", ["pending_payment", "created"])
      .lt("created_at", cutoff)
      .limit(500);

    if (findErr) throw findErr;
    const ids = (stuck ?? []).map((o) => o.id);

    if (ids.length === 0) {
      return new Response(
        JSON.stringify({ ok: true, expired: 0 }),
        { headers: { ...corsHeaders, "Content-Type": "application/json" } },
      );
    }

    // Release any reserved player-board spaces tied to these orders
    await supabase
      .from("competition_spaces")
      .update({
        status: "available",
        user_id: null,
        order_id: null,
        reserved_at: null,
        reserved_until: null,
      })
      .in("order_id", ids)
      .eq("status", "reserved");

    // Mark the orders as expired
    const { error: updErr } = await supabase
      .from("orders")
      .update({ status: "expired", updated_at: new Date().toISOString() })
      .in("id", ids);
    if (updErr) throw updErr;

    // Also expire any reservations whose hold has lapsed (covers non-order TTLs)
    await supabase.rpc("expire_space_reservations");

    return new Response(
      JSON.stringify({ ok: true, expired: ids.length, ids }),
      { headers: { ...corsHeaders, "Content-Type": "application/json" } },
    );
  } catch (e) {
    console.error("expire-stuck-orders error", e);
    return new Response(
      JSON.stringify({ ok: false, error: String(e?.message ?? e) }),
      { status: 500, headers: { ...corsHeaders, "Content-Type": "application/json" } },
    );
  }
});

````


---

## mailchimp-sync

- **Purpose / logic:** Legacy Mailchimp audience sync of marketing-opted-in profiles. Not scheduled; Reads MAILCHIMP_API_KEY and MAILCHIMP_AUDIENCE_ID; invoked from AuthContext (signup), Account (marketing opt-in toggle) and AdminEmailCampaigns; silently no-ops if MAILCHIMP_API_KEY is unset (unclear whether set — Brevo is now the marketing tool per recent exports).
- **Trigger & auth:** Manual admin call (JWT + has_role admin).
- **Inputs:** —
- **Outputs / external calls:** Mailchimp API calls.

### `supabase/functions/mailchimp-sync/index.ts`

````ts
// Mailchimp sync edge function
// Modes:
//   - "subscribe": add/update a single subscriber (requires email; name optional)
//   - "backfill": admin-only — sync all opted-in profiles to Mailchimp
import { createClient } from 'npm:@supabase/supabase-js@2.49.4';

const corsHeaders = {
  'Access-Control-Allow-Origin': '*',
  'Access-Control-Allow-Headers': 'authorization, x-client-info, apikey, content-type',
};

const MAILCHIMP_API_KEY = Deno.env.get('MAILCHIMP_API_KEY');
const MAILCHIMP_AUDIENCE_ID = Deno.env.get('MAILCHIMP_AUDIENCE_ID');
const SUPABASE_URL = Deno.env.get('SUPABASE_URL')!;
const SUPABASE_SERVICE_ROLE_KEY = Deno.env.get('SUPABASE_SERVICE_ROLE_KEY')!;
const SUPABASE_ANON_KEY = Deno.env.get('SUPABASE_ANON_KEY')!;

function getServer(): string | null {
  if (!MAILCHIMP_API_KEY) return null;
  const parts = MAILCHIMP_API_KEY.split('-');
  return parts.length >= 2 ? parts[parts.length - 1] : null;
}

// Minimal MD5 implementation (Mailchimp uses MD5 hash of lowercased email as subscriber id)
function md5Hex(input: string): string {
  const msg = input.toLowerCase().trim();
  // deno-lint-ignore no-explicit-any
  function add32(a: number, b: number) { return (a + b) & 0xffffffff; }
  function cmn(q: number, a: number, b: number, x: number, s: number, t: number) {
    a = add32(add32(a, q), add32(x, t));
    return add32((a << s) | (a >>> (32 - s)), b);
  }
  function ff(a: number, b: number, c: number, d: number, x: number, s: number, t: number) {
    return cmn((b & c) | (~b & d), a, b, x, s, t);
  }
  function gg(a: number, b: number, c: number, d: number, x: number, s: number, t: number) {
    return cmn((b & d) | (c & ~d), a, b, x, s, t);
  }
  function hh(a: number, b: number, c: number, d: number, x: number, s: number, t: number) {
    return cmn(b ^ c ^ d, a, b, x, s, t);
  }
  function ii(a: number, b: number, c: number, d: number, x: number, s: number, t: number) {
    return cmn(c ^ (b | ~d), a, b, x, s, t);
  }
  function md51(s: string) {
    const n = s.length;
    const state = [1732584193, -271733879, -1732584194, 271733878];
    let i;
    const bytes: number[] = [];
    for (i = 0; i < n; i++) bytes.push(s.charCodeAt(i) & 0xff);
    bytes.push(0x80);
    while (bytes.length % 64 !== 56) bytes.push(0);
    const bitLen = n * 8;
    bytes.push(bitLen & 0xff, (bitLen >>> 8) & 0xff, (bitLen >>> 16) & 0xff, (bitLen >>> 24) & 0xff, 0, 0, 0, 0);
    for (i = 0; i < bytes.length; i += 64) {
      const blk: number[] = [];
      for (let j = 0; j < 16; j++) {
        blk[j] = bytes[i + j * 4] | (bytes[i + j * 4 + 1] << 8) | (bytes[i + j * 4 + 2] << 16) | (bytes[i + j * 4 + 3] << 24);
      }
      let [a, b, c, d] = state;
      a = ff(a, b, c, d, blk[0], 7, -680876936); d = ff(d, a, b, c, blk[1], 12, -389564586); c = ff(c, d, a, b, blk[2], 17, 606105819); b = ff(b, c, d, a, blk[3], 22, -1044525330);
      a = ff(a, b, c, d, blk[4], 7, -176418897); d = ff(d, a, b, c, blk[5], 12, 1200080426); c = ff(c, d, a, b, blk[6], 17, -1473231341); b = ff(b, c, d, a, blk[7], 22, -45705983);
      a = ff(a, b, c, d, blk[8], 7, 1770035416); d = ff(d, a, b, c, blk[9], 12, -1958414417); c = ff(c, d, a, b, blk[10], 17, -42063); b = ff(b, c, d, a, blk[11], 22, -1990404162);
      a = ff(a, b, c, d, blk[12], 7, 1804603682); d = ff(d, a, b, c, blk[13], 12, -40341101); c = ff(c, d, a, b, blk[14], 17, -1502002290); b = ff(b, c, d, a, blk[15], 22, 1236535329);
      a = gg(a, b, c, d, blk[1], 5, -165796510); d = gg(d, a, b, c, blk[6], 9, -1069501632); c = gg(c, d, a, b, blk[11], 14, 643717713); b = gg(b, c, d, a, blk[0], 20, -373897302);
      a = gg(a, b, c, d, blk[5], 5, -701558691); d = gg(d, a, b, c, blk[10], 9, 38016083); c = gg(c, d, a, b, blk[15], 14, -660478335); b = gg(b, c, d, a, blk[4], 20, -405537848);
      a = gg(a, b, c, d, blk[9], 5, 568446438); d = gg(d, a, b, c, blk[14], 9, -1019803690); c = gg(c, d, a, b, blk[3], 14, -187363961); b = gg(b, c, d, a, blk[8], 20, 1163531501);
      a = gg(a, b, c, d, blk[13], 5, -1444681467); d = gg(d, a, b, c, blk[2], 9, -51403784); c = gg(c, d, a, b, blk[7], 14, 1735328473); b = gg(b, c, d, a, blk[12], 20, -1926607734);
      a = hh(a, b, c, d, blk[5], 4, -378558); d = hh(d, a, b, c, blk[8], 11, -2022574463); c = hh(c, d, a, b, blk[11], 16, 1839030562); b = hh(b, c, d, a, blk[14], 23, -35309556);
      a = hh(a, b, c, d, blk[1], 4, -1530992060); d = hh(d, a, b, c, blk[4], 11, 1272893353); c = hh(c, d, a, b, blk[7], 16, -155497632); b = hh(b, c, d, a, blk[10], 23, -1094730640);
      a = hh(a, b, c, d, blk[13], 4, 681279174); d = hh(d, a, b, c, blk[0], 11, -358537222); c = hh(c, d, a, b, blk[3], 16, -722521979); b = hh(b, c, d, a, blk[6], 23, 76029189);
      a = hh(a, b, c, d, blk[9], 4, -640364487); d = hh(d, a, b, c, blk[12], 11, -421815835); c = hh(c, d, a, b, blk[15], 16, 530742520); b = hh(b, c, d, a, blk[2], 23, -995338651);
      a = ii(a, b, c, d, blk[0], 6, -198630844); d = ii(d, a, b, c, blk[7], 10, 1126891415); c = ii(c, d, a, b, blk[14], 15, -1416354905); b = ii(b, c, d, a, blk[5], 21, -57434055);
      a = ii(a, b, c, d, blk[12], 6, 1700485571); d = ii(d, a, b, c, blk[3], 10, -1894986606); c = ii(c, d, a, b, blk[10], 15, -1051523); b = ii(b, c, d, a, blk[1], 21, -2054922799);
      a = ii(a, b, c, d, blk[8], 6, 1873313359); d = ii(d, a, b, c, blk[15], 10, -30611744); c = ii(c, d, a, b, blk[6], 15, -1560198380); b = ii(b, c, d, a, blk[13], 21, 1309151649);
      a = ii(a, b, c, d, blk[4], 6, -145523070); d = ii(d, a, b, c, blk[11], 10, -1120210379); c = ii(c, d, a, b, blk[2], 15, 718787259); b = ii(b, c, d, a, blk[9], 21, -343485551);
      state[0] = add32(state[0], a); state[1] = add32(state[1], b); state[2] = add32(state[2], c); state[3] = add32(state[3], d);
    }
    return state;
  }
  function rhex(n: number) {
    let s = '';
    for (let j = 0; j < 4; j++) s += ((n >> (j * 8 + 4)) & 0x0f).toString(16) + ((n >> (j * 8)) & 0x0f).toString(16);
    return s;
  }
  return md51(msg).map(rhex).join('');
}

async function upsertSubscriber(email: string, fullName?: string | null) {
  const server = getServer();
  if (!server || !MAILCHIMP_AUDIENCE_ID) {
    throw new Error('Mailchimp not configured (missing API key or audience ID)');
  }

  const hash = md5Hex(email);
  const url = `https://${server}.api.mailchimp.com/3.0/lists/${MAILCHIMP_AUDIENCE_ID}/members/${hash}`;

  const [firstName, ...rest] = (fullName || '').trim().split(/\s+/);
  const lastName = rest.join(' ');

  const body = {
    email_address: email,
    status_if_new: 'subscribed',
    status: 'subscribed',
    merge_fields: {
      ...(firstName ? { FNAME: firstName } : {}),
      ...(lastName ? { LNAME: lastName } : {}),
    },
  };

  const res = await fetch(url, {
    method: 'PUT',
    headers: {
      'Content-Type': 'application/json',
      Authorization: `Basic ${btoa(`anystring:${MAILCHIMP_API_KEY}`)}`,
    },
    body: JSON.stringify(body),
  });

  const text = await res.text();
  if (!res.ok) {
    throw new Error(`Mailchimp ${res.status}: ${text}`);
  }
  return JSON.parse(text);
}

Deno.serve(async (req) => {
  if (req.method === 'OPTIONS') {
    return new Response('ok', { headers: corsHeaders });
  }

  try {
    if (!MAILCHIMP_API_KEY || !MAILCHIMP_AUDIENCE_ID) {
      return new Response(
        JSON.stringify({ error: 'Mailchimp not configured' }),
        { status: 500, headers: { ...corsHeaders, 'Content-Type': 'application/json' } },
      );
    }

    const { mode, email, fullName } = await req.json().catch(() => ({}));

    if (mode === 'subscribe') {
      if (!email || typeof email !== 'string') {
        return new Response(
          JSON.stringify({ error: 'email is required' }),
          { status: 400, headers: { ...corsHeaders, 'Content-Type': 'application/json' } },
        );
      }
      // Require a valid signed-in user, and only allow subscribing their own email.
      const authHeader = req.headers.get('Authorization');
      if (!authHeader?.startsWith('Bearer ')) {
        return new Response(JSON.stringify({ error: 'Unauthorized' }), {
          status: 401, headers: { ...corsHeaders, 'Content-Type': 'application/json' },
        });
      }
      const userClient = createClient(SUPABASE_URL, SUPABASE_ANON_KEY, {
        global: { headers: { Authorization: authHeader } },
      });
      const token = authHeader.replace('Bearer ', '');
      const { data: claimsData, error: claimsError } = await userClient.auth.getClaims(token);
      const claimEmail = (claimsData?.claims as any)?.email as string | undefined;
      if (claimsError || !claimEmail || claimEmail.toLowerCase() !== email.toLowerCase()) {
        return new Response(JSON.stringify({ error: 'Forbidden' }), {
          status: 403, headers: { ...corsHeaders, 'Content-Type': 'application/json' },
        });
      }
      const result = await upsertSubscriber(email, fullName);
      return new Response(
        JSON.stringify({ success: true, id: result.id, status: result.status }),
        { headers: { ...corsHeaders, 'Content-Type': 'application/json' } },
      );
    }

    if (mode === 'backfill') {
      // Admin-only: verify caller is admin
      const authHeader = req.headers.get('Authorization');
      if (!authHeader?.startsWith('Bearer ')) {
        return new Response(JSON.stringify({ error: 'Unauthorized' }), {
          status: 401,
          headers: { ...corsHeaders, 'Content-Type': 'application/json' },
        });
      }
      const userClient = createClient(SUPABASE_URL, SUPABASE_ANON_KEY, {
        global: { headers: { Authorization: authHeader } },
      });
      const token = authHeader.replace('Bearer ', '');
      const { data: claimsData, error: claimsError } = await userClient.auth.getClaims(token);
      if (claimsError || !claimsData?.claims) {
        return new Response(JSON.stringify({ error: 'Unauthorized' }), {
          status: 401,
          headers: { ...corsHeaders, 'Content-Type': 'application/json' },
        });
      }
      const userId = claimsData.claims.sub;

      const adminClient = createClient(SUPABASE_URL, SUPABASE_SERVICE_ROLE_KEY);
      const { data: isAdminData } = await adminClient.rpc('has_role', {
        _user_id: userId,
        _role: 'admin',
      });
      if (!isAdminData) {
        return new Response(JSON.stringify({ error: 'Forbidden' }), {
          status: 403,
          headers: { ...corsHeaders, 'Content-Type': 'application/json' },
        });
      }

      // Fetch all opted-in profiles
      const { data: profiles, error: profErr } = await adminClient
        .from('profiles')
        .select('email, full_name')
        .eq('marketing_opt_in', true);
      if (profErr) throw profErr;

      let synced = 0;
      let failed = 0;
      const errors: string[] = [];
      for (const p of profiles || []) {
        try {
          await upsertSubscriber(p.email, p.full_name);
          synced++;
        } catch (e) {
          failed++;
          errors.push(`${p.email}: ${(e as Error).message}`);
        }
      }

      return new Response(
        JSON.stringify({
          success: true,
          total: profiles?.length || 0,
          synced,
          failed,
          errors: errors.slice(0, 10),
        }),
        { headers: { ...corsHeaders, 'Content-Type': 'application/json' } },
      );
    }

    return new Response(
      JSON.stringify({ error: 'Unknown mode. Use "subscribe" or "backfill"' }),
      { status: 400, headers: { ...corsHeaders, 'Content-Type': 'application/json' } },
    );
  } catch (e) {
    console.error('mailchimp-sync error:', e);
    return new Response(
      JSON.stringify({ error: (e as Error).message }),
      { status: 500, headers: { ...corsHeaders, 'Content-Type': 'application/json' } },
    );
  }
});

````


---

## order-status

- **Purpose / logic:** Polled by /payment/success. Returns order status and, once the webhook has finished instant-win processing (metadata.instantWinProcessedAt), a per-ticket list of instant-win results (wins and explicit No Win) plus game type per competition; returns instantWinPending=true until then.
- **Trigger & auth:** Browser GET ?orderNumber=… every 2s (max 15 polls). Requires bearer token; only the order owner can read.
- **Inputs:** orderNumber query param.
- **Outputs / external calls:** { status, amount, num_items, instantWinPending?, instantWinResults?[] }

### `supabase/functions/order-status/index.ts`

````ts
import { serve } from "https://deno.land/std@0.190.0/http/server.ts";
import { createClient } from "npm:@supabase/supabase-js@2.49.4";

const corsHeaders = {
  "Access-Control-Allow-Origin": "*",
  "Access-Control-Allow-Headers": "authorization, x-client-info, apikey, content-type, x-supabase-client-platform, x-supabase-client-platform-version, x-supabase-client-runtime, x-supabase-client-runtime-version",
};

serve(async (req) => {
  if (req.method === "OPTIONS") {
    return new Response(null, { headers: corsHeaders });
  }

  const supabaseAdmin = createClient(
    Deno.env.get("SUPABASE_URL") ?? "",
    Deno.env.get("SUPABASE_SERVICE_ROLE_KEY") ?? "",
    { auth: { persistSession: false } }
  );

  try {
    // Require a valid signed-in user
    const authHeader = req.headers.get("Authorization") || "";
    if (!authHeader.startsWith("Bearer ")) {
      return new Response(JSON.stringify({ error: "Unauthorized" }), {
        headers: { ...corsHeaders, "Content-Type": "application/json" }, status: 401
      });
    }
    const userClient = createClient(
      Deno.env.get("SUPABASE_URL") ?? "",
      Deno.env.get("SUPABASE_ANON_KEY") ?? Deno.env.get("SUPABASE_PUBLISHABLE_KEY") ?? "",
      { global: { headers: { Authorization: authHeader } } }
    );
    const { data: { user } } = await userClient.auth.getUser();
    if (!user) {
      return new Response(JSON.stringify({ error: "Unauthorized" }), {
        headers: { ...corsHeaders, "Content-Type": "application/json" }, status: 401
      });
    }

    const url = new URL(req.url);
    const orderNumber = url.searchParams.get("orderNumber");

    if (!orderNumber || !/^VN-[A-Z0-9]+-[A-Z0-9]+$/.test(orderNumber)) {
      return new Response(JSON.stringify({ error: "Invalid order number" }), {
        headers: { ...corsHeaders, "Content-Type": "application/json" }, status: 400
      });
    }

    const { data: order, error } = await supabaseAdmin
      .from("orders")
      .select("status, updated_at, order_number, amount, entry_ids, user_id, metadata")
      .eq("order_number", orderNumber)
      .eq("user_id", user.id)
      .maybeSingle();

    if (error || !order) {
      return new Response(JSON.stringify({ error: "Order not found" }), {
        headers: { ...corsHeaders, "Content-Type": "application/json" }, status: 404
      });
    }

    const response: any = {
      status: order.status,
      updatedAt: order.updated_at,
      orderNumber: order.order_number,
      amount: order.amount,
      num_items: order.entry_ids?.length || 1,
    };

    // If paid, build a per-ticket instant win reveal list (wins + explicit "No Win" tickets)
    if (order.status === "paid" && order.entry_ids && order.entry_ids.length > 0) {
      const { data: orderEntries } = await supabaseAdmin
        .from("entries")
        .select("id, competition_id, ticket_count")
        .in("id", order.entry_ids);

      const compIds = [...new Set((orderEntries || []).map((e) => e.competition_id))];
      let instantWinComps = new Set<string>();
      const gameByComp: Record<string, string> = {};
      if (compIds.length > 0) {
        const { data: comps } = await supabaseAdmin
          .from("competitions")
          .select("id, is_instant_win, instant_win_game")
          .in("id", compIds);
        instantWinComps = new Set((comps || []).filter((c) => c.is_instant_win).map((c) => c.id));
        for (const c of comps || []) gameByComp[c.id] = (c as any).instant_win_game || "classic";
      }

      const iwEntries = (orderEntries || []).filter((e) => instantWinComps.has(e.competition_id));

      // The webhook draws each instant-win ticket AFTER the order is marked paid.
      // Until it flags completion, results are incomplete — never show them, or a
      // winning ticket would be revealed as "No Win".
      const iwProcessed = Boolean((order.metadata as any)?.instantWinProcessedAt);
      if (iwEntries.length > 0 && !iwProcessed) {
        response.instantWinPending = true;
      }

      if (iwEntries.length > 0 && iwProcessed) {
        const { data: iwResults } = await supabaseAdmin
          .from("instant_win_results")
          .select("id, is_winner, prize_id, entry_id")
          .eq("user_id", order.user_id)
          .in("entry_id", iwEntries.map((e) => e.id));

        const winnerRows = (iwResults || []).filter((r) => r.is_winner);
        const prizeIds = winnerRows.filter((r) => r.prize_id).map((r) => r.prize_id);
        let prizesMap: Record<string, any> = {};
        if (prizeIds.length > 0) {
          const { data: prizes } = await supabaseAdmin
            .from("instant_win_prizes")
            .select("id, prize_name, prize_value, prize_type")
            .in("id", prizeIds);
          if (prizes) prizesMap = Object.fromEntries(prizes.map((p) => [p.id, p]));
        }

        const results: any[] = [];
        for (const entry of iwEntries) {
          const wins = winnerRows.filter((r) => r.entry_id === entry.id);
          for (const w of wins) {
            const prize = w.prize_id ? prizesMap[w.prize_id] : null;
            results.push({
              entry_id: entry.id,
              game: gameByComp[entry.competition_id] || "classic",
              is_winner: true,
              ...(prize
                ? {
                    prize_name: prize.prize_name,
                    prize_value: prize.prize_value,
                    prize_type: prize.prize_type,
                  }
                : {}),
            });
          }
          // Every remaining ticket on this entry is an explicit "No Win"
          const losers = Math.max(0, (entry.ticket_count || 0) - wins.length);
          for (let i = 0; i < losers; i++) results.push({ entry_id: entry.id, game: gameByComp[entry.competition_id] || "classic", is_winner: false });
        }

        if (results.length > 0) response.instantWinResults = results;
      }
    }

    return new Response(JSON.stringify(response), {
      headers: { ...corsHeaders, "Content-Type": "application/json" },
    });

  } catch (error) {
    return new Response(JSON.stringify({ error: "Internal error" }), {
      headers: { ...corsHeaders, "Content-Type": "application/json" }, status: 500
    });
  }
});
````


---

## player-board-checkout

- **Purpose / logic:** Checkout for player-board competitions (pick a named space). Reserves competition_spaces (reserved_until), enforces max_entries_per_user, promo, site credit, spend limits, creates order and Cashflows payment job; or completes immediately if fully credit-paid and auto-draws when board sells out.
- **Trigger & auth:** Browser via usePlayerBoard / CartSheet. Bearer token validated.
- **Inputs:** { competitionId, spaceIds[], promoCode?, creditAmount? }
- **Outputs / external calls:** { url, orderNumber } or completion JSON.

### `supabase/functions/player-board-checkout/index.ts`

````ts
import { serve } from "https://deno.land/std@0.190.0/http/server.ts";
import { createClient } from "npm:@supabase/supabase-js@2.49.4";

const corsHeaders = {
  "Access-Control-Allow-Origin": "*",
  "Access-Control-Allow-Headers": "authorization, x-client-info, apikey, content-type, x-supabase-client-platform, x-supabase-client-platform-version, x-supabase-client-runtime, x-supabase-client-runtime-version",
};

const log = (step: string, details?: any) => {
  const d = details ? ` - ${JSON.stringify(details)}` : '';
  console.log(`[PLAYER-BOARD-CHECKOUT] ${step}${d}`);
};

function getBaseUrl(): string {
  const env = Deno.env.get("CASHFLOWS_ENV") || "integration";
  const override = Deno.env.get("CASHFLOWS_BASE_URL");
  if (override) return override.replace(/\/$/, '');
  return env === "production"
    ? "https://gateway.cashflows.com"
    : "https://gateway-int.cashflows.com";
}

async function createHash(apiKey: string, bodyString: string): Promise<string> {
  const encoder = new TextEncoder();
  const data = encoder.encode(apiKey + bodyString);
  const hashBuffer = await crypto.subtle.digest("SHA-512", data);
  return Array.from(new Uint8Array(hashBuffer)).map(b => b.toString(16).padStart(2, '0')).join('');
}

function generateOrderNumber(): string {
  const ts = Date.now().toString(36).toUpperCase();
  const rand = Math.random().toString(36).substring(2, 6).toUpperCase();
  return `VN-${ts}-${rand}`;
}

const UUID_REGEX = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;
const RESERVATION_MINUTES = 15;

serve(async (req) => {
  if (req.method === "OPTIONS") {
    return new Response(null, { headers: corsHeaders });
  }

  const supabaseAdmin = createClient(
    Deno.env.get("SUPABASE_URL") ?? "",
    Deno.env.get("SUPABASE_SERVICE_ROLE_KEY") ?? "",
    { auth: { persistSession: false } }
  );

  const supabaseClient = createClient(
    Deno.env.get("SUPABASE_URL") ?? "",
    Deno.env.get("SUPABASE_ANON_KEY") ?? ""
  );

  try {
    log("Function started");

    const configId = Deno.env.get("CASHFLOWS_CONFIGURATION_ID");
    const apiKey = Deno.env.get("CASHFLOWS_API_KEY");
    if (!configId || !apiKey) throw new Error("Cashflows credentials not configured");

    const siteBaseUrl = Deno.env.get("SITE_BASE_URL") || "https://vaultninecompetitions.co.uk";

    // Auth
    const authHeader = req.headers.get("Authorization");
    if (!authHeader) {
      return new Response(JSON.stringify({ error: "Unauthorized" }), {
        headers: { ...corsHeaders, "Content-Type": "application/json" }, status: 401
      });
    }

    const token = authHeader.replace("Bearer ", "");
    const { data: userData, error: userError } = await supabaseClient.auth.getUser(token);
    if (userError || !userData.user?.email) {
      return new Response(JSON.stringify({ error: "Authentication failed" }), {
        headers: { ...corsHeaders, "Content-Type": "application/json" }, status: 401
      });
    }
    const user = userData.user;
    log("User authenticated", { userId: user.id });

    // Parse body
    let body: any;
    try { body = await req.json(); } catch {
      return new Response(JSON.stringify({ error: "Invalid JSON" }), {
        headers: { ...corsHeaders, "Content-Type": "application/json" }, status: 400
      });
    }

    const { competitionId, spaceIds, promoCode, creditAmount } = body;

    // Validate inputs
    if (!competitionId || !UUID_REGEX.test(competitionId)) {
      return new Response(JSON.stringify({ error: "Invalid competition ID" }), {
        headers: { ...corsHeaders, "Content-Type": "application/json" }, status: 400
      });
    }
    if (!Array.isArray(spaceIds) || spaceIds.length === 0 || spaceIds.length > 200) {
      return new Response(JSON.stringify({ error: "Invalid space selection" }), {
        headers: { ...corsHeaders, "Content-Type": "application/json" }, status: 400
      });
    }
    for (const id of spaceIds) {
      if (!UUID_REGEX.test(id)) {
        return new Response(JSON.stringify({ error: "Invalid space ID" }), {
          headers: { ...corsHeaders, "Content-Type": "application/json" }, status: 400
        });
      }
    }

    // Expire old reservations first
    await supabaseAdmin.rpc('expire_space_reservations');

    // Get competition
    const { data: competition, error: compError } = await supabaseAdmin
      .from("competitions")
      .select("*")
      .eq("id", competitionId)
      .eq("competition_type", "player_board")
      .single();

    if (compError || !competition) {
      return new Response(JSON.stringify({ error: "Player board competition not found" }), {
        headers: { ...corsHeaders, "Content-Type": "application/json" }, status: 404
      });
    }

    if (competition.status !== 'active') {
      return new Response(JSON.stringify({ error: "Competition is not active" }), {
        headers: { ...corsHeaders, "Content-Type": "application/json" }, status: 400
      });
    }

    // Check max entries per user
    if (competition.max_entries_per_user) {
      const { count } = await supabaseAdmin
        .from("competition_spaces")
        .select("id", { count: "exact" })
        .eq("competition_id", competitionId)
        .eq("user_id", user.id)
        .in("status", ["reserved", "sold"]);

      if ((count || 0) + spaceIds.length > competition.max_entries_per_user) {
        return new Response(JSON.stringify({
          error: `Maximum ${competition.max_entries_per_user} spaces per person. You already have ${count || 0}.`
        }), { headers: { ...corsHeaders, "Content-Type": "application/json" }, status: 400 });
      }
    }

    // Validate spaces exist and are available
    const { data: spaces, error: spacesError } = await supabaseAdmin
      .from("competition_spaces")
      .select("*")
      .eq("competition_id", competitionId)
      .in("id", spaceIds);

    if (spacesError || !spaces || spaces.length !== spaceIds.length) {
      return new Response(JSON.stringify({ error: "Some spaces not found" }), {
        headers: { ...corsHeaders, "Content-Type": "application/json" }, status: 400
      });
    }

    const unavailable = spaces.filter(s => s.status !== 'available');
    if (unavailable.length > 0) {
      return new Response(JSON.stringify({
        error: "Some spaces are no longer available",
        unavailableSpaces: unavailable.map(s => ({ number: s.space_number, status: s.status }))
      }), { headers: { ...corsHeaders, "Content-Type": "application/json" }, status: 409 });
    }

    // Reserve spaces with expiry
    const now = new Date();
    const reservedUntil = new Date(now.getTime() + RESERVATION_MINUTES * 60 * 1000);

    const { error: reserveError } = await supabaseAdmin
      .from("competition_spaces")
      .update({
        status: "reserved",
        user_id: user.id,
        reserved_at: now.toISOString(),
        reserved_until: reservedUntil.toISOString(),
        updated_at: now.toISOString()
      })
      .in("id", spaceIds)
      .eq("status", "available");

    if (reserveError) {
      log("Failed to reserve spaces", { error: reserveError.message });
      return new Response(JSON.stringify({ error: "Failed to reserve spaces - they may have been taken" }), {
        headers: { ...corsHeaders, "Content-Type": "application/json" }, status: 409
      });
    }

    log("Spaces reserved", { count: spaceIds.length, reservedUntil: reservedUntil.toISOString() });

    // Calculate total
    const effectivePrice = (competition.sale_price != null && Number(competition.sale_price) < Number(competition.ticket_price))
      ? Number(competition.sale_price)
      : Number(competition.ticket_price);
    let totalAmount = effectivePrice * spaceIds.length;

    // Apply promo code
    let appliedPromo: any = null;
    if (promoCode && typeof promoCode === 'string') {
      const { data: promo } = await supabaseAdmin
        .from("promo_codes")
        .select("*")
        .eq("code", promoCode.trim().toUpperCase())
        .eq("is_active", true)
        .maybeSingle();

      if (promo && (!promo.max_uses || promo.times_used < promo.max_uses) &&
          (!promo.valid_until || new Date(promo.valid_until) > new Date())) {
        if (promo.discount_type === 'percentage') {
          totalAmount = totalAmount * (1 - promo.discount_value / 100);
        } else {
          totalAmount = Math.max(0, totalAmount - promo.discount_value);
        }
        totalAmount = Math.round(totalAmount * 100) / 100;
        appliedPromo = promo;

        await supabaseAdmin.from("promo_codes").update({ times_used: promo.times_used + 1 }).eq("id", promo.id);
        log("Promo applied", { code: promo.code });
      }
    }

    // Apply site credit
    let creditUsed = 0;
    if (creditAmount && typeof creditAmount === 'number' && creditAmount > 0) {
      const { data: balanceData } = await supabaseAdmin.rpc('get_user_credit_balance', { p_user_id: user.id });
      const availableCredit = Number(balanceData) || 0;
      creditUsed = Math.min(creditAmount, availableCredit, totalAmount);
      creditUsed = Math.round(creditUsed * 100) / 100;
      if (creditUsed > 0) {
        totalAmount = Math.round((totalAmount - creditUsed) * 100) / 100;
      }
    }

    // Responsible Play: check user spend limits / cool-off / self-exclusion
    if (totalAmount > 0) {
      const { data: limitCheck } = await supabaseAdmin.rpc('check_user_spend_limit', {
        p_user_id: user.id,
        p_amount: totalAmount,
      });
      if (limitCheck && (limitCheck as any).allowed === false) {
        const lc: any = limitCheck;
        return new Response(JSON.stringify({
          error: lc.message || 'Purchase blocked by your Responsible Play settings.',
          responsiblePlay: true,
          reason: lc.reason,
        }), { headers: { ...corsHeaders, "Content-Type": "application/json" }, status: 403 });
      }
    }

    // Look up influencer

    let influencerId: string | null = null;
    if (appliedPromo) {
      const { data: influencer } = await supabaseAdmin
        .from("influencers")
        .select("id")
        .eq("promo_code_id", appliedPromo.id)
        .eq("is_active", true)
        .maybeSingle();
      if (influencer) influencerId = influencer.id;
    }

    const orderNumber = generateOrderNumber();

    // Create order with player_board metadata
    const { data: order, error: orderError } = await supabaseAdmin
      .from("orders")
      .insert({
        order_number: orderNumber,
        user_id: user.id,
        amount: totalAmount,
        currency: "GBP",
        status: "pending_payment",
        competition_ids: [competitionId],
        promo_code: appliedPromo?.code || null,
        influencer_id: influencerId,
        metadata: {
          type: "player_board",
          competitionId,
          spaceIds,
          spaces: spaces.map(s => ({ id: s.id, number: s.space_number, playerName: s.player_name })),
          userEmail: user.email,
          creditUsed: creditUsed || 0
        }
      })
      .select("id")
      .single();

    if (orderError || !order) {
      // Release reservations on failure
      await supabaseAdmin.from("competition_spaces")
        .update({ status: "available", user_id: null, order_id: null, reserved_at: null, reserved_until: null, updated_at: new Date().toISOString() })
        .in("id", spaceIds);
      log("Failed to create order", { error: orderError?.message });
      return new Response(JSON.stringify({ error: "Failed to create order" }), {
        headers: { ...corsHeaders, "Content-Type": "application/json" }, status: 500
      });
    }

    // Link order to spaces
    await supabaseAdmin.from("competition_spaces").update({ order_id: order.id }).in("id", spaceIds);

    log("Order created", { orderNumber, amount: totalAmount, spaces: spaceIds.length });

    // If fully covered by credit, complete immediately
    if (totalAmount <= 0 && creditUsed > 0) {
      // Mark spaces as sold
      await supabaseAdmin.from("competition_spaces")
        .update({ status: "sold", reserved_at: null, reserved_until: null, updated_at: new Date().toISOString() })
        .in("id", spaceIds);

      // Create entries
      const entryInserts = spaces.map(s => ({
        user_id: user.id,
        competition_id: competitionId,
        ticket_count: 1,
        amount_paid: 0,
        payment_status: "completed"
      }));

      const { data: entries } = await supabaseAdmin.from("entries").insert(entryInserts).select("id");
      const entryIds = (entries || []).map(e => e.id);

      // Link entries to spaces
      for (let i = 0; i < spaces.length && i < entryIds.length; i++) {
        await supabaseAdmin.from("competition_spaces").update({ entry_id: entryIds[i] }).eq("id", spaces[i].id);
      }

      // Deduct credit (atomic to prevent over-spending)
      const { data: deductedAmt, error: deductErr } = await supabaseAdmin.rpc('deduct_site_credit', {
        p_user_id: user.id,
        p_amount: creditUsed,
        p_reason: `Order ${orderNumber}`,
        p_order_id: order.id,
      });
      if (deductErr) {
        log("Credit deduction error", { error: deductErr.message });
      }

      // Mark order paid
      await supabaseAdmin.from("orders").update({
        status: "paid", entry_ids: entryIds,
      }).eq("id", order.id);

      // Check if all spaces sold → auto-draw
      const { count: totalSpaces } = await supabaseAdmin.from("competition_spaces")
        .select("id", { count: "exact" }).eq("competition_id", competitionId);
      const { count: soldSpaces } = await supabaseAdmin.from("competition_spaces")
        .select("id", { count: "exact" }).eq("competition_id", competitionId).eq("status", "sold");

      if (soldSpaces === totalSpaces && totalSpaces && totalSpaces > 0) {
        const { data: drawResult } = await supabaseAdmin.rpc('player_board_auto_draw', { p_competition_id: competitionId });
        log("Auto-draw triggered (credit)", drawResult);

        // Send winner email
        if (drawResult?.success && drawResult?.winner_email) {
          try {
            await fetch(`${Deno.env.get("SUPABASE_URL")}/functions/v1/send-email`, {
              method: "POST",
              headers: {
                "Content-Type": "application/json",
                "Authorization": `Bearer ${Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")}`,
              },
              body: JSON.stringify({
                type: "player_board_winner",
                to: drawResult.winner_email,
                data: {
                  winnerName: drawResult.winner_name,
                  competitionTitle: competition.title,
                  prizeDescription: competition.description,
                  winningSpaceNumber: drawResult.winner_space_number,
                  winningPlayerName: drawResult.winner_player_name,
                  siteUrl: siteBaseUrl,
                },
              }),
            });
          } catch (e) { log("Failed to send winner email", { error: String(e) }); }
        }
      }

      return new Response(JSON.stringify({
        success: true, message: `Order paid with £${creditUsed.toFixed(2)} site credit`,
        entryIds
      }), { headers: { ...corsHeaders, "Content-Type": "application/json" } });
    }

    // Get user profile for Cashflows
    const { data: profile } = await supabaseAdmin
      .from("profiles")
      .select("full_name")
      .eq("user_id", user.id)
      .single();

    const names = (profile?.full_name || "").split(" ");
    const firstName = names[0] || "";
    const lastName = names.slice(1).join(" ") || "";

    // Build Cashflows payload
    const cashflowsPayload = {
      type: "Payment",
      paymentMethodsToUse: ["creditcard"],
      amountToCollect: totalAmount.toFixed(2),
      currency: "GBP",
      locale: "en_GB",
      order: {
        orderNumber: orderNumber,
        billingAddress: { firstName, lastName },
        billingIdentity: { emailAddress: user.email },
      },
      parameters: {
        returnUrlSuccess: `${siteBaseUrl}/payment/success?order=${orderNumber}`,
        returnUrlFailed: `${siteBaseUrl}/payment/failed?order=${orderNumber}`,
        returnUrlCancelled: `${siteBaseUrl}/payment/cancelled?order=${orderNumber}`,
      }
    };

    const minifiedBody = JSON.stringify(cashflowsPayload);
    const hash = await createHash(apiKey, minifiedBody);
    const baseUrl = getBaseUrl();

    log("Calling Cashflows", { baseUrl, orderNumber });

    const cfResponse = await fetch(`${baseUrl}/api/gateway/payment-jobs`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "ConfigurationId": configId,
        "Hash": hash,
      },
      body: minifiedBody,
    });

    const cfData = await cfResponse.json();
    log("Cashflows response", { status: cfResponse.status, data: cfData });

    if (!cfResponse.ok) {
      // Release reservations
      await supabaseAdmin.from("competition_spaces")
        .update({ status: "available", user_id: null, order_id: null, reserved_at: null, reserved_until: null })
        .in("id", spaceIds);
      await supabaseAdmin.from("orders").update({ status: "failed" }).eq("id", order.id);
      throw new Error(`Cashflows error: ${JSON.stringify(cfData)}`);
    }

    const cfInner = cfData?.data || cfData;
    const actionUrl =
      cfInner?.actionUrl ||
      cfInner?.url ||
      cfInner?.links?.action?.url ||
      cfData?.links?.action?.url ||
      cfData?.data?.links?.action?.url;
    const paymentJobRef =
      cfInner?.paymentJobReference ||
      cfInner?.reference ||
      cfData?.data?.reference ||
      cfData?.reference;

    if (!actionUrl) {
      await supabaseAdmin.from("competition_spaces")
        .update({ status: "available", user_id: null, order_id: null, reserved_at: null, reserved_until: null })
        .in("id", spaceIds);
      throw new Error("No checkout URL from Cashflows");
    }

    // Store Cashflows references
    await supabaseAdmin.from("orders").update({
      cashflows_action_url: actionUrl,
      cashflows_payment_job_reference: paymentJobRef,
    }).eq("id", order.id);

    log("Checkout created", { orderNumber, actionUrl: actionUrl?.substring(0, 60) });

    return new Response(JSON.stringify({
      url: actionUrl,
      orderNumber,
    }), {
      headers: { ...corsHeaders, "Content-Type": "application/json" },
    });

  } catch (error) {
    const msg = error instanceof Error ? error.message : String(error);
    log("ERROR", { message: msg });
    return new Response(JSON.stringify({ error: msg }), {
      headers: { ...corsHeaders, "Content-Type": "application/json" },
      status: 500,
    });
  }
});

````


---

## publish-scheduled-competitions

- **Purpose / logic:** Legacy: flips scheduled competitions to active. Superseded by pg_cron job 13 direct SQL; not currently scheduled.
- **Trigger & auth:** CRON_SECRET_KEY header.
- **Inputs:** none
- **Outputs / external calls:** count

### `supabase/functions/publish-scheduled-competitions/index.ts`

````ts
import { createClient } from "npm:@supabase/supabase-js@2.49.4";

const corsHeaders = {
  "Access-Control-Allow-Origin": "*",
  "Access-Control-Allow-Headers": "authorization, x-client-info, apikey, content-type",
};

Deno.serve(async (req) => {
  if (req.method === "OPTIONS") {
    return new Response("ok", { headers: corsHeaders });
  }

  // Auth: only CRON_SECRET_KEY or service role can publish scheduled competitions.
  const authHeader = req.headers.get("Authorization") || "";
  const token = authHeader.replace(/^Bearer\s+/i, "");
  const cron = Deno.env.get("CRON_SECRET_KEY");
  const svc = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY");
  if (!token || ((!cron || token !== cron) && (!svc || token !== svc))) {
    return new Response(JSON.stringify({ error: "Unauthorized" }), {
      status: 401,
      headers: { ...corsHeaders, "Content-Type": "application/json" },
    });
  }

  try {
    const supabase = createClient(
      Deno.env.get("SUPABASE_URL")!,
      Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!,
    );

    const nowIso = new Date().toISOString();

    const { data, error } = await supabase
      .from("competitions")
      .update({ status: "active", updated_at: nowIso })
      .eq("status", "scheduled")
      .lte("scheduled_start_at", nowIso)
      .select("id, title, scheduled_start_at");

    if (error) throw error;

    return new Response(
      JSON.stringify({ published: data?.length ?? 0, competitions: data ?? [] }),
      { headers: { ...corsHeaders, "Content-Type": "application/json" } },
    );
  } catch (err) {
    console.error("publish-scheduled-competitions error:", err);
    return new Response(
      JSON.stringify({ error: String((err as Error).message ?? err) }),
      { status: 500, headers: { ...corsHeaders, "Content-Type": "application/json" } },
    );
  }
});

````


---

## send-bulk-email

- **Purpose / logic:** Admin marketing campaign sender via Resend to opted-in users, logs to email_logs.
- **Trigger & auth:** Admin UI (AdminEmailCampaigns). verify_jwt=true + admin check.
- **Inputs:** { subject, html/body, audience }
- **Outputs / external calls:** Resend API; email_logs inserts.

### `supabase/functions/send-bulk-email/index.ts`

````ts
import { serve } from "https://deno.land/std@0.190.0/http/server.ts";
import { createClient } from "npm:@supabase/supabase-js@2.49.4";

const corsHeaders = {
  "Access-Control-Allow-Origin": "*",
  "Access-Control-Allow-Headers": "authorization, x-client-info, apikey, content-type, x-supabase-client-platform, x-supabase-client-platform-version, x-supabase-client-runtime, x-supabase-client-runtime-version",
};

// Input validation constants
const MAX_SUBJECT_LENGTH = 200;
const MAX_CONTENT_LENGTH = 50000;
const EMAIL_REGEX = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

// Simple HTML sanitization - removes script tags and dangerous attributes
const sanitizeHtml = (html: string): string => {
  return html
    // Remove script tags and their content
    .replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '')
    // Remove onclick, onerror, onload, and other event handlers
    .replace(/\s+on\w+\s*=\s*["'][^"']*["']/gi, '')
    .replace(/\s+on\w+\s*=\s*[^\s>]+/gi, '')
    // Remove javascript: URLs
    .replace(/javascript:/gi, '')
    // Remove data: URLs in src/href (potential XSS vector)
    .replace(/\s+(src|href)\s*=\s*["']?\s*data:/gi, ' $1="')
    // Remove style expressions (IE specific XSS)
    .replace(/expression\s*\(/gi, '')
    // Remove vbscript: URLs
    .replace(/vbscript:/gi, '');
};

const isValidEmail = (email: unknown): email is string => {
  return typeof email === 'string' && EMAIL_REGEX.test(email) && email.length <= 255;
};

const isValidSubject = (subject: unknown): subject is string => {
  return typeof subject === 'string' && 
         subject.trim().length > 0 && 
         subject.length <= MAX_SUBJECT_LENGTH;
};

const isValidContent = (content: unknown): content is string => {
  return typeof content === 'string' && 
         content.trim().length > 0 && 
         content.length <= MAX_CONTENT_LENGTH;
};

const sendEmail = async (to: string, subject: string, html: string, apiKey: string, fromEmail: string) => {
  const response = await fetch("https://api.resend.com/emails", {
    method: "POST",
    headers: {
      "Authorization": `Bearer ${apiKey}`,
      "Content-Type": "application/json",
    },
    body: JSON.stringify({
      from: fromEmail,
      to: [to],
      subject,
      html,
    }),
  });

  if (!response.ok) {
    const error = await response.json();
    throw new Error(error.message || "Failed to send email");
  }

  return response.json();
};

const handler = async (req: Request): Promise<Response> => {
  console.log("[SEND-BULK-EMAIL] Function called");

  if (req.method === "OPTIONS") {
    return new Response(null, { headers: corsHeaders });
  }

  try {
    const supabaseUrl = Deno.env.get("SUPABASE_URL")!;
    const supabaseServiceKey = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!;
    const supabase = createClient(supabaseUrl, supabaseServiceKey);

    const resendApiKey = Deno.env.get("RESEND_API_KEY");
    if (!resendApiKey) {
      throw new Error("RESEND_API_KEY not configured");
    }

    // Verify admin authorization
    const authHeader = req.headers.get("authorization");
    if (!authHeader) {
      return new Response(JSON.stringify({ error: "No authorization header" }), {
        status: 401,
        headers: { "Content-Type": "application/json", ...corsHeaders },
      });
    }

    const token = authHeader.replace("Bearer ", "");
    const { data: { user }, error: authError } = await supabase.auth.getUser(token);
    
    if (authError || !user) {
      return new Response(JSON.stringify({ error: "Unauthorized" }), {
        status: 401,
        headers: { "Content-Type": "application/json", ...corsHeaders },
      });
    }

    // Check if user is admin using the has_role function
    const { data: isAdmin, error: roleError } = await supabase.rpc('has_role', {
      _user_id: user.id,
      _role: 'admin'
    });

    if (roleError || !isAdmin) {
      console.log("[SEND-BULK-EMAIL] Admin access denied");
      return new Response(JSON.stringify({ error: "Admin access required" }), {
        status: 403,
        headers: { "Content-Type": "application/json", ...corsHeaders },
      });
    }

    // Parse and validate request body
    let body: unknown;
    try {
      body = await req.json();
    } catch {
      return new Response(JSON.stringify({ error: "Invalid JSON body" }), {
        status: 400,
        headers: { "Content-Type": "application/json", ...corsHeaders },
      });
    }

    const { subject, content, testEmail } = body as Record<string, unknown>;

    // Validate subject
    if (!isValidSubject(subject)) {
      console.log("[SEND-BULK-EMAIL] Invalid subject");
      return new Response(JSON.stringify({ 
        error: `Subject is required and must be less than ${MAX_SUBJECT_LENGTH} characters` 
      }), {
        status: 400,
        headers: { "Content-Type": "application/json", ...corsHeaders },
      });
    }

    // Validate content
    if (!isValidContent(content)) {
      console.log("[SEND-BULK-EMAIL] Invalid content");
      return new Response(JSON.stringify({ 
        error: `Content is required and must be less than ${MAX_CONTENT_LENGTH} characters` 
      }), {
        status: 400,
        headers: { "Content-Type": "application/json", ...corsHeaders },
      });
    }

    // Validate test email if provided
    if (testEmail !== undefined && !isValidEmail(testEmail)) {
      console.log("[SEND-BULK-EMAIL] Invalid test email");
      return new Response(JSON.stringify({ error: "Invalid test email format" }), {
        status: 400,
        headers: { "Content-Type": "application/json", ...corsHeaders },
      });
    }

    // Sanitize HTML content to prevent XSS
    const sanitizedSubject = subject.trim().substring(0, MAX_SUBJECT_LENGTH);
    const sanitizedContent = sanitizeHtml(content);

    console.log(`[SEND-BULK-EMAIL] Sending email: ${sanitizedSubject.substring(0, 50)}...`);

    const siteUrl = Deno.env.get("SITE_URL") || "https://vaultnine.com";
    const fromEmail = Deno.env.get("FROM_EMAIL") || "VaultNine <onboarding@resend.dev>";

    // If test email provided, send only to that address
    if (testEmail) {
      console.log(`[SEND-BULK-EMAIL] Sending test email to ${testEmail}`);
      
      const emailHtml = `
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
          <div style="background: #B11226; color: white; padding: 10px; text-align: center; margin-bottom: 20px; border-radius: 6px;">
            ⚠️ TEST EMAIL - This is a preview
          </div>
          <div style="background: linear-gradient(135deg, #0F3D1F 0%, #1F6B3A 100%); padding: 30px; border-radius: 10px 10px 0 0;">
            <span style="font-family: Georgia, serif; font-size: 24px; font-weight: bold;"><span style="color: #1F6B3A;">Vault</span><span style="color: #F4F4F0;">Nine</span></span>
            <span style="display: block; font-size: 11px; letter-spacing: 3px; text-transform: uppercase; color: #9A9A9A; margin-top: 4px;">Competitions</span>
            <h1 style="color: #F4F4F0; margin: 16px 0 0; font-size: 24px;">${sanitizedSubject}</h1>
          </div>
          <div style="background: #161616; padding: 30px; color: #F4F4F0; border-radius: 0 0 10px 10px; border: 1px solid #2A2A2A; border-top: 0;">
            ${sanitizedContent}
            <hr style="border: none; border-top: 1px solid #2A2A2A; margin: 30px 0;" />
            <p style="color: #6A6A6A; font-size: 12px; text-align: center;">
              You received this email because you opted in to marketing communications.<br/>
              <a href="${siteUrl}/account" style="color: #1F6B3A;">Manage your preferences</a>
            </p>
          </div>
        </div>
      `;

      await sendEmail(testEmail, `[TEST] ${sanitizedSubject}`, emailHtml, resendApiKey, fromEmail);

      return new Response(JSON.stringify({ success: true, testSent: true }), {
        status: 200,
        headers: { "Content-Type": "application/json", ...corsHeaders },
      });
    }

    // Get all users who have opted in to marketing
    const { data: subscribers, error: fetchError } = await supabase
      .from("profiles")
      .select("email, user_id")
      .eq("marketing_opt_in", true);

    if (fetchError) {
      throw fetchError;
    }

    if (!subscribers || subscribers.length === 0) {
      return new Response(JSON.stringify({ success: true, sent: 0, message: "No subscribers found" }), {
        status: 200,
        headers: { "Content-Type": "application/json", ...corsHeaders },
      });
    }

    console.log(`[SEND-BULK-EMAIL] Found ${subscribers.length} subscribers`);

    const emailHtml = `
      <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
        <div style="background: linear-gradient(135deg, #0F3D1F 0%, #1F6B3A 100%); padding: 30px; border-radius: 10px 10px 0 0;">
          <span style="font-family: Georgia, serif; font-size: 24px; font-weight: bold;"><span style="color: #1F6B3A;">Vault</span><span style="color: #F4F4F0;">Nine</span></span>
          <span style="display: block; font-size: 11px; letter-spacing: 3px; text-transform: uppercase; color: #9A9A9A; margin-top: 4px;">Competitions</span>
          <h1 style="color: #F4F4F0; margin: 16px 0 0; font-size: 24px;">${sanitizedSubject}</h1>
        </div>
        <div style="background: #161616; padding: 30px; color: #F4F4F0; border-radius: 0 0 10px 10px; border: 1px solid #2A2A2A; border-top: 0;">
          ${sanitizedContent}
          <hr style="border: none; border-top: 1px solid #2A2A2A; margin: 30px 0;" />
          <p style="color: #6A6A6A; font-size: 12px; text-align: center;">
            You received this email because you opted in to marketing communications.<br/>
            <a href="${siteUrl}/account" style="color: #1F6B3A;">Manage your preferences</a>
          </p>
        </div>
      </div>
    `;

    let sentCount = 0;
    const errors: string[] = [];

    // Send emails in batches to avoid rate limits
    for (const subscriber of subscribers) {
      try {
        await sendEmail(subscriber.email, sanitizedSubject, emailHtml, resendApiKey, fromEmail);

        // Log the email
        await supabase.from("email_logs").insert({
          recipient_email: subscriber.email,
          recipient_user_id: subscriber.user_id,
          email_type: "marketing",
          subject: sanitizedSubject,
          status: "sent",
          metadata: { bulk: true },
        });

        sentCount++;
      } catch (err: any) {
        console.error(`[SEND-BULK-EMAIL] Failed to send to ${subscriber.email}`);
        errors.push(subscriber.email);
      }

      // Small delay to avoid rate limiting
      await new Promise((resolve) => setTimeout(resolve, 100));
    }

    console.log(`[SEND-BULK-EMAIL] Sent ${sentCount} emails, ${errors.length} failures`);

    return new Response(
      JSON.stringify({
        success: true,
        sent: sentCount,
        failed: errors.length,
        errors: errors.length > 0 ? errors : undefined,
      }),
      {
        status: 200,
        headers: { "Content-Type": "application/json", ...corsHeaders },
      }
    );
  } catch (error: any) {
    console.error("[SEND-BULK-EMAIL] Error:", error.message);
    return new Response(
      JSON.stringify({ error: "An error occurred processing your request" }),
      {
        status: 500,
        headers: { "Content-Type": "application/json", ...corsHeaders },
      }
    );
  }
};

serve(handler);

````


---

## send-draw-reminders

- **Purpose / logic:** Emails entrants of competitions drawing soon (reminder), deduplicated via email_logs.
- **Trigger & auth:** pg_cron job 4 hourly with Bearer token / CRON_SECRET_KEY.
- **Inputs:** none
- **Outputs / external calls:** Calls send-email; counts.

### `supabase/functions/send-draw-reminders/index.ts`

````ts
import { serve } from "https://deno.land/std@0.190.0/http/server.ts";
import { createClient } from "npm:@supabase/supabase-js@2.49.4";

const corsHeaders = {
  "Access-Control-Allow-Origin": "*",
  "Access-Control-Allow-Headers": "authorization, x-client-info, apikey, content-type, x-supabase-client-platform, x-supabase-client-platform-version, x-supabase-client-runtime, x-supabase-client-runtime-version",
};

const logStep = (step: string, details?: any) => {
  const detailsStr = details ? ` - ${JSON.stringify(details)}` : '';
  console.log(`[DRAW-REMINDERS] ${step}${detailsStr}`);
};

serve(async (req) => {
  if (req.method === "OPTIONS") {
    return new Response(null, { headers: corsHeaders });
  }

  // Verify authorization - only CRON_SECRET_KEY or SUPABASE_SERVICE_ROLE_KEY.
  // The anon/publishable key must NOT authorize bulk email triggering.
  const authHeader = req.headers.get("Authorization");
  const cronSecret = Deno.env.get("CRON_SECRET_KEY");
  const serviceRoleKey = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY");

  const isValidCronSecret = cronSecret && authHeader === `Bearer ${cronSecret}`;
  const isValidServiceRole = serviceRoleKey && authHeader === `Bearer ${serviceRoleKey}`;

  if (!authHeader || (!isValidCronSecret && !isValidServiceRole)) {
    logStep("Unauthorized request - invalid or missing authorization");
    return new Response(JSON.stringify({ error: "Unauthorized" }), {
      headers: { ...corsHeaders, "Content-Type": "application/json" },
      status: 401,
    });
  }

  logStep("Authorization successful");

  const supabaseAdmin = createClient(
    Deno.env.get("SUPABASE_URL") ?? "",
    Deno.env.get("SUPABASE_SERVICE_ROLE_KEY") ?? ""
  );

  try {
    logStep("Function started");

    const now = new Date();
    const in24Hours = new Date(now.getTime() + 24 * 60 * 60 * 1000);
    const in25Hours = new Date(now.getTime() + 25 * 60 * 60 * 1000);

    logStep("Checking for competitions", { 
      from: in24Hours.toISOString(), 
      to: in25Hours.toISOString() 
    });

    // Find competitions ending in the next 24-25 hours (1 hour window to avoid duplicates)
    const { data: competitions, error: compError } = await supabaseAdmin
      .from("competitions")
      .select("id, title, draw_date")
      .eq("status", "active")
      .gte("draw_date", in24Hours.toISOString())
      .lt("draw_date", in25Hours.toISOString());

    if (compError) {
      throw new Error(`Failed to fetch competitions: ${compError.message}`);
    }

    if (!competitions || competitions.length === 0) {
      logStep("No competitions found in reminder window");
      return new Response(JSON.stringify({ 
        success: true, 
        message: "No competitions need reminders",
        competitionsChecked: 0 
      }), {
        headers: { ...corsHeaders, "Content-Type": "application/json" },
        status: 200,
      });
    }

    logStep("Found competitions needing reminders", { count: competitions.length });

    let totalEmailsSent = 0;
    const siteUrl = Deno.env.get("SITE_URL") || "https://ljxgflavyooynamjrzzt.lovableproject.com";

    for (const competition of competitions) {
      const drawDate = new Date(competition.draw_date).toLocaleDateString('en-GB', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      });

      // Get paid entries with user profiles
      const { data: paidEntries, error: paidError } = await supabaseAdmin
        .from("entries")
        .select("id, user_id, ticket_count")
        .eq("competition_id", competition.id)
        .eq("payment_status", "completed");

      if (paidError) {
        logStep("Error fetching paid entries", { error: paidError.message });
        continue;
      }

      // Send to paid entrants
      for (const entry of paidEntries || []) {
        // Check if reminder already sent
        const { data: existingLog } = await supabaseAdmin
          .from("email_logs")
          .select("id")
          .eq("email_type", "draw_reminder")
          .eq("recipient_user_id", entry.user_id)
          .contains("metadata", { competition_id: competition.id })
          .limit(1);

        if (existingLog && existingLog.length > 0) {
          continue; // Already sent
        }

        // Get user profile
        const { data: profile } = await supabaseAdmin
          .from("profiles")
          .select("email, full_name")
          .eq("user_id", entry.user_id)
          .single();

        if (!profile?.email) continue;

        try {
          // Send email via send-email function
          await fetch(`${Deno.env.get("SUPABASE_URL")}/functions/v1/send-email`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Authorization': `Bearer ${Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")}`
            },
            body: JSON.stringify({
              type: 'draw_reminder',
              to: profile.email,
              data: {
                competitionTitle: competition.title,
                ticketCount: entry.ticket_count,
                drawDate,
                siteUrl,
                userId: entry.user_id,
                competition_id: competition.id
              }
            })
          });

          totalEmailsSent++;
          logStep("Sent reminder to paid entrant", { email: profile.email });
        } catch (emailErr) {
          logStep("Failed to send email", { error: String(emailErr) });
        }
      }

      // Get postal entries
      const { data: postalEntries, error: postalError } = await supabaseAdmin
        .from("postal_entries")
        .select("id, email, full_name, ticket_count")
        .eq("competition_id", competition.id);

      if (postalError) {
        logStep("Error fetching postal entries", { error: postalError.message });
        continue;
      }

      // Send to postal entrants
      for (const entry of postalEntries || []) {
        // Check if reminder already sent
        const { data: existingLog } = await supabaseAdmin
          .from("email_logs")
          .select("id")
          .eq("email_type", "draw_reminder")
          .eq("recipient_email", entry.email)
          .contains("metadata", { competition_id: competition.id })
          .limit(1);

        if (existingLog && existingLog.length > 0) {
          continue; // Already sent
        }

        try {
          await fetch(`${Deno.env.get("SUPABASE_URL")}/functions/v1/send-email`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Authorization': `Bearer ${Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")}`
            },
            body: JSON.stringify({
              type: 'draw_reminder',
              to: entry.email,
              data: {
                competitionTitle: competition.title,
                ticketCount: entry.ticket_count,
                drawDate,
                siteUrl,
                competition_id: competition.id
              }
            })
          });

          totalEmailsSent++;
          logStep("Sent reminder to postal entrant", { email: entry.email });
        } catch (emailErr) {
          logStep("Failed to send email", { error: String(emailErr) });
        }
      }
    }

    logStep("Completed", { totalEmailsSent });

    return new Response(JSON.stringify({ 
      success: true, 
      message: `Sent ${totalEmailsSent} reminder emails`,
      competitionsProcessed: competitions.length,
      emailsSent: totalEmailsSent
    }), {
      headers: { ...corsHeaders, "Content-Type": "application/json" },
      status: 200,
    });

  } catch (error) {
    const errorMessage = error instanceof Error ? error.message : String(error);
    logStep("ERROR", { message: errorMessage });
    return new Response(JSON.stringify({ error: errorMessage }), {
      headers: { ...corsHeaders, "Content-Type": "application/json" },
      status: 500,
    });
  }
});
````


---

## send-email

- **Purpose / logic:** Central transactional email sender (Resend). Templates: order/entry confirmation, instant win, winner notification, ID verification, contact form, marketing, etc. Logs to email_logs.
- **Trigger & auth:** Internal calls with service-role bearer from other functions; restricted authenticated calls (ownership / admin) and the public contact form path (fixed recipient).
- **Inputs:** { type, to, data }
- **Outputs / external calls:** Resend API; email_logs.

### `supabase/functions/send-email/index.ts`

````ts
import { serve } from "https://deno.land/std@0.190.0/http/server.ts";
import { createClient } from "npm:@supabase/supabase-js@2.49.4";

const corsHeaders = {
  "Access-Control-Allow-Origin": "*",
  "Access-Control-Allow-Headers": "authorization, x-client-info, apikey, content-type, x-supabase-client-platform, x-supabase-client-platform-version, x-supabase-client-runtime, x-supabase-client-runtime-version",
};

interface EmailRequest {
  type: "entry_confirmation" | "winner_notification" | "player_board_winner" | "player_board_confirmation" | "marketing" | "welcome" | "draw_reminder" | "draw_complete" | "contact" | "password_reset";
  to: string;
  data: Record<string, any>;
}

const GREEN = "#0F3D1F";
const GREEN_LIGHT = "#1F6B3A";
const BLACK = "#1a1a1a";
const GREY = "#666666";
const BORDER = "#e0e0e0";
const FONT = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";

const LOGO_URL = "https://ljxgflavyooynamjrzzt.supabase.co/storage/v1/object/public/competition-images/brand%2Flogo.svg";
const SITE_URL = "https://vaultninecompetitions.co.uk";

// Escape HTML special chars so untrusted input (e.g. contact form fields)
// cannot inject markup into outgoing emails.
const esc = (v: unknown): string =>
  String(v ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');

const btn = (text: string, href: string) => `
  <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" style="margin: 24px 0;">
    <tr>
      <td style="background: ${GREEN}; border-radius: 6px;">
        <a href="${href}" style="display: inline-block; padding: 12px 28px; color: #ffffff; text-decoration: none; font-family: ${FONT}; font-size: 14px; font-weight: 600;">${text}</a>
      </td>
    </tr>
  </table>
`;

const wrap = (content: string, siteUrl: string = SITE_URL) => `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>VaultNine Competitions</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f5f5f5;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #f5f5f5;">
    <tr>
      <td align="center" style="padding: 30px 15px;">
        <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="max-width: 600px; width: 100%; background: #ffffff; border-radius: 8px; overflow: hidden; border: 1px solid ${BORDER};">
          <!-- Logo Header -->
          <tr>
            <td style="padding: 30px 30px 20px 30px; text-align: center; border-bottom: 2px solid ${GREEN};">
              <a href="${siteUrl}" style="text-decoration: none;">
                <img src="${LOGO_URL}" alt="VaultNine Competitions" width="180" height="50" style="display: block; margin: 0 auto; max-width: 180px; height: auto;" />
              </a>
            </td>
          </tr>
          <!-- Content -->
          <tr>
            <td style="padding: 30px; font-family: ${FONT}; font-size: 15px; line-height: 1.6; color: ${BLACK};">
              ${content}
            </td>
          </tr>
          <!-- Footer -->
          <tr>
            <td style="padding: 20px 30px; text-align: center; border-top: 1px solid ${BORDER}; background: #fafafa;">
              <p style="margin: 0 0 8px 0; font-family: ${FONT}; font-size: 12px; color: ${GREY};">
                <a href="${siteUrl}" style="color: ${GREEN_LIGHT}; text-decoration: none;">Website</a> &nbsp;•&nbsp;
                <a href="${siteUrl}/competitions" style="color: ${GREEN_LIGHT}; text-decoration: none;">Competitions</a> &nbsp;•&nbsp;
                <a href="${siteUrl}/contact" style="color: ${GREEN_LIGHT}; text-decoration: none;">Contact</a>
              </p>
              <p style="margin: 0; font-family: ${FONT}; font-size: 11px; color: #999999;">
                © ${new Date().getFullYear()} VaultNine Competitions. All rights reserved. UK residents only • 18+
              </p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>`;

const row = (label: string, value: string) => `
  <tr>
    <td style="padding: 8px 0; border-bottom: 1px solid ${BORDER};">
      <span style="color: ${GREY}; font-size: 13px;">${label}</span><br>
      <span style="color: ${BLACK}; font-size: 15px; font-weight: 600;">${value}</span>
    </td>
  </tr>
`;

const getEmailContent = (type: string, data: Record<string, any>) => {
  const siteUrl = data.siteUrl || SITE_URL;

  switch (type) {
    case "entry_confirmation":
      return {
        subject: `Entry Confirmed - ${data.competitionTitle} | VaultNine Competitions`,
        html: wrap(`
          <h1 style="color: ${GREEN}; font-size: 22px; margin: 0 0 16px 0;">Entry Confirmed</h1>
          <p style="margin: 0 0 20px 0;">Great news! Your entry has been successfully confirmed.</p>
          
          <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin: 20px 0;">
            <tr><td style="padding: 12px; background: #f8f8f8; border-radius: 6px; border-left: 4px solid ${GREEN};">
              <strong style="color: ${GREEN}; font-size: 17px;">${data.competitionTitle}</strong>
            </td></tr>
          </table>

          <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
            ${row('Tickets Purchased', data.ticketCount)}
            ${data.ticketNumbers ? row('Ticket Numbers', data.ticketNumbers) : ''}
            ${data.drawDate ? row('Draw Date', data.drawDate) : ''}
            ${data.totalAmount ? row('Amount Paid', `£${data.totalAmount}`) : ''}
          </table>

          <p style="margin: 20px 0 0 0; color: ${GREY}; font-size: 14px;">Good luck! We'll notify you when the winner is drawn.</p>

          ${btn('View My Entries', `${siteUrl}/account`)}
        `, siteUrl),
      };

    case "instant_win_confirmation": {
      const prizesWon = data.instantWinPrizes && data.instantWinPrizes.length > 0;
      const prizeListHtml = prizesWon
        ? data.instantWinPrizes.map((p: string) => `<li style="padding: 6px 0; font-size: 15px; color: ${BLACK};">🎉 <strong>${p}</strong></li>`).join('')
        : '';

      return {
        subject: prizesWon
          ? `You Won! 🎉 ${data.competitionTitle} | VaultNine Competitions`
          : `Your Instant Win Results - ${data.competitionTitle} | VaultNine Competitions`,
        html: wrap(`
          <h1 style="color: ${GREEN}; font-size: 22px; margin: 0 0 16px 0;">
            ${prizesWon ? '🎉 Congratulations — You Won!' : 'Your Instant Win Results'}
          </h1>

          <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin: 20px 0;">
            <tr><td style="padding: 12px; background: #f8f8f8; border-radius: 6px; border-left: 4px solid ${GREEN};">
              <strong style="color: ${GREEN}; font-size: 17px;">${data.competitionTitle}</strong>
            </td></tr>
          </table>

          <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
            ${row('Tickets Purchased', data.ticketCount)}
            ${data.ticketNumbers ? row('Ticket Numbers', data.ticketNumbers) : ''}
          </table>

          ${prizesWon ? `
            <div style="margin: 24px 0; padding: 16px; background: #f0fdf4; border-radius: 8px; border: 1px solid #bbf7d0;">
              <p style="margin: 0 0 12px 0; font-weight: 600; color: ${GREEN}; font-size: 16px;">Prizes Won:</p>
              <ul style="margin: 0; padding: 0 0 0 20px; list-style: none;">
                ${prizeListHtml}
              </ul>
            </div>
            <p style="margin: 20px 0 0 0; color: ${GREY}; font-size: 14px;">We'll be in touch to arrange any physical prizes. Site credit has been added to your account automatically.</p>
          ` : `
            <p style="margin: 20px 0 0 0; color: ${GREY}; font-size: 14px;">Better luck next time! There are still prizes waiting to be won — grab more tickets and try again.</p>
          `}

          ${btn('View My Entries', `${siteUrl}/account`)}
        `, siteUrl),
      };
    }

    case "winner_notification":
      return {
        subject: `Congratulations! You've Won - ${data.competitionTitle} | VaultNine Competitions`,
        html: wrap(`
          <h1 style="color: ${GREEN}; font-size: 24px; margin: 0 0 16px 0; text-align: center;">Congratulations — You've Won!</h1>
          <p style="text-align: center; font-size: 16px; margin: 0 0 24px 0;">${data.winnerName ? `Well done <strong>${data.winnerName}</strong>!` : 'Well done!'}</p>
          
          <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin: 20px 0;">
            <tr><td style="padding: 16px; background: #f8f8f8; border-radius: 6px; text-align: center; border: 1px solid ${BORDER};">
              <strong style="color: ${GREEN}; font-size: 18px;">${data.competitionTitle}</strong><br>
              <span style="color: ${BLACK}; font-size: 15px;">${data.prizeDescription}</span>
            </td></tr>
          </table>

          <p style="margin: 20px 0; font-size: 14px;"><strong style="color: ${GREEN};">What happens next?</strong><br>We'll be in touch shortly to arrange delivery of your prize. Please ensure your contact details are up to date.</p>

          ${btn('View My Account', `${siteUrl}/account`)}
        `, siteUrl),
      };

    case "welcome":
      return {
        subject: "Welcome to VaultNine Competitions!",
        html: wrap(`
          <h1 style="color: ${GREEN}; font-size: 22px; margin: 0 0 16px 0;">Welcome to VaultNine!</h1>
          <p style="margin: 0 0 20px 0;">Thanks for joining the UK's premier darts prize competition!</p>
          
          <p style="margin: 0 0 8px 0;"><strong style="color: ${GREEN};">1.</strong> Browse our live competitions</p>
          <p style="margin: 0 0 8px 0;"><strong style="color: ${GREEN};">2.</strong> Answer a simple skill question</p>
          <p style="margin: 0 0 20px 0;"><strong style="color: ${GREEN};">3.</strong> Win amazing darts prizes!</p>
          
          <p style="margin: 0 0 0 0; color: ${GREY}; font-size: 13px;">No purchase necessary — free postal entry is available for all competitions.</p>

          ${btn('View Competitions', `${siteUrl}/competitions`)}
        `, siteUrl),
      };

    case "marketing":
      return {
        subject: data.subject || "News from VaultNine Competitions",
        html: wrap(`
          <h1 style="color: ${GREEN}; font-size: 22px; margin: 0 0 16px 0;">${data.subject || 'News from VaultNine'}</h1>
          <div style="font-size: 15px; line-height: 1.7; color: ${BLACK};">${data.content}</div>
          <p style="margin: 24px 0 0 0; color: #999; font-size: 12px; border-top: 1px solid ${BORDER}; padding-top: 16px;">
            You received this because you opted in to marketing. <a href="${siteUrl}/account" style="color: ${GREEN_LIGHT};">Manage preferences</a>
          </p>
        `, siteUrl),
      };

    case "draw_reminder":
      return {
        subject: `Draw Tomorrow - ${data.competitionTitle} | VaultNine Competitions`,
        html: wrap(`
          <h1 style="color: ${GREEN}; font-size: 22px; margin: 0 0 16px 0;">Draw Happening Tomorrow!</h1>
          <p style="margin: 0 0 20px 0;">The draw for your competition is happening soon.</p>
          
          <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin: 20px 0;">
            <tr><td style="padding: 12px; background: #f8f8f8; border-radius: 6px; border-left: 4px solid ${GREEN};">
              <strong style="color: ${GREEN}; font-size: 17px;">${data.competitionTitle}</strong>
            </td></tr>
          </table>

          <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
            ${row('Your Tickets', data.ticketCount)}
            ${row('Draw Date', data.drawDate)}
          </table>

          <p style="margin: 20px 0 0 0; text-align: center; font-size: 15px;">Good luck! We'll email you if you win.</p>

          ${btn('Enter More Competitions', `${siteUrl}/competitions`)}
        `, siteUrl),
      };

    case "draw_complete":
      return {
        subject: `Draw Complete - ${data.competitionTitle} | VaultNine Competitions`,
        html: wrap(`
          <h1 style="color: ${GREEN}; font-size: 22px; margin: 0 0 16px 0; text-align: center;">The Draw is Complete</h1>
          <p style="margin: 0 0 20px 0; text-align: center;">The winner has been drawn for:</p>

          <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin: 16px 0;">
            <tr><td style="padding: 14px; background: #f8f8f8; border-radius: 6px; text-align: center; border-left: 4px solid ${GREEN};">
              <strong style="color: ${GREEN}; font-size: 17px;">${data.competitionTitle}</strong>
            </td></tr>
          </table>

          <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
            ${row('Winning Ticket', `#${data.winningTicketNumber}`)}
            ${row('Winner', data.winnerName || 'Announced shortly')}
          </table>

          <p style="margin: 24px 0 0 0; text-align: center; font-size: 14px; color: ${GREY};">
            Thanks for entering! Plenty more prizes waiting — your next ticket could be the winner.
          </p>

          ${btn('Enter More Competitions', `${siteUrl}/competitions`)}
        `, siteUrl),
      };

    case "contact":
      return {
        subject: `Contact Form: ${esc(data.subject)} | VaultNine Competitions`,
        html: wrap(`
          <h1 style="color: ${GREEN}; font-size: 22px; margin: 0 0 16px 0;">New Contact Form Submission</h1>
          
          <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
            ${row('From', esc(data.name))}
            ${row('Email', esc(data.email))}
            ${row('Subject', esc(data.subject))}
          </table>
          
          <div style="margin: 20px 0; padding: 16px; background: #f8f8f8; border-radius: 6px;">
            <span style="color: ${GREY}; font-size: 13px;">Message</span>
            <p style="margin: 8px 0 0 0; white-space: pre-wrap;">${esc(data.message)}</p>
          </div>
        `),
      };

    case "password_reset":
      return {
        subject: "Reset Your Password | VaultNine Competitions",
        html: wrap(`
          <h1 style="color: ${GREEN}; font-size: 22px; margin: 0 0 16px 0; text-align: center;">Password Reset</h1>
          <p style="text-align: center; margin: 0 0 24px 0;">We received a request to reset your password.</p>
          
          ${btn('Reset Password', data.resetLink)}
          
          <p style="text-align: center; color: ${GREY}; font-size: 13px; margin: 0;">If you didn't request this, you can safely ignore this email. This link expires in 24 hours.</p>
        `, siteUrl),
      };

    case "player_board_winner":
      return {
        subject: `Congratulations! You've Won Space #${data.winningSpaceNumber} - ${data.competitionTitle} | VaultNine Competitions`,
        html: wrap(`
          <h1 style="color: ${GREEN}; font-size: 24px; margin: 0 0 16px 0; text-align: center;">Congratulations — You've Won!</h1>
          <p style="text-align: center; font-size: 16px; margin: 0 0 24px 0;">${data.winnerName ? `Well done <strong>${data.winnerName}</strong>!` : 'Well done!'}</p>
          
          <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin: 20px 0;">
            <tr><td style="padding: 16px; background: #f8f8f8; border-radius: 6px; text-align: center; border: 1px solid ${BORDER};">
              <strong style="color: ${GREEN}; font-size: 18px;">${data.competitionTitle}</strong><br>
              <span style="color: ${BLACK}; font-size: 15px;">${data.prizeDescription || 'Amazing Prize'}</span>
            </td></tr>
          </table>

          <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
            ${row('Winning Space', `#${data.winningSpaceNumber}`)}
            ${row('Player Name', data.winningPlayerName)}
          </table>

          <p style="margin: 20px 0; font-size: 14px;"><strong style="color: ${GREEN};">What happens next?</strong><br>We'll be in touch within 48 hours to arrange delivery of your prize. Please ensure your account details are up to date.</p>

          ${btn('View My Account', `${siteUrl}/account`)}
        `, siteUrl),
      };

    case "player_board_confirmation":
      return {
        subject: `Spaces Confirmed - ${data.competitionTitle} | VaultNine Competitions`,
        html: wrap(`
          <h1 style="color: ${GREEN}; font-size: 22px; margin: 0 0 16px 0;">Spaces Confirmed</h1>
          <p style="margin: 0 0 20px 0;">${data.customerName ? `Hey <strong>${data.customerName}</strong>! ` : ''}Your player board spaces have been confirmed.</p>
          
          <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin: 20px 0;">
            <tr><td style="padding: 12px; background: #f8f8f8; border-radius: 6px; border-left: 4px solid ${GREEN};">
              <strong style="color: ${GREEN}; font-size: 17px;">${data.competitionTitle}</strong>
            </td></tr>
          </table>

          <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
            ${row('Spaces Purchased', data.spaceCount)}
            ${row('Your Spaces', data.spacesList)}
            ${row('Order Number', data.orderNumber)}
            ${row('Amount Paid', data.amountPaid)}
            ${row('Winner Selected', 'Auto-draw when all spaces sold')}
          </table>

          <p style="margin: 20px 0 0 0; color: ${GREY}; font-size: 14px;">Good luck! Once all spaces are sold, the winner will be drawn automatically.</p>

          ${btn('View My Entries', `${siteUrl}/account`)}
        `, siteUrl),
      };

    default:
      throw new Error(`Unknown email type: ${type}`);
  }
};

const sendEmail = async (to: string, subject: string, html: string, apiKey: string, fromEmail: string) => {
  const response = await fetch("https://api.resend.com/emails", {
    method: "POST",
    headers: {
      "Authorization": `Bearer ${apiKey}`,
      "Content-Type": "application/json",
    },
    body: JSON.stringify({
      from: fromEmail,
      to: [to],
      subject,
      html,
    }),
  });

  if (!response.ok) {
    const error = await response.json();
    throw new Error(error.message || "Failed to send email");
  }

  return response.json();
};

const UNAUTHENTICATED_TYPES = new Set(["contact"]);

const handler = async (req: Request): Promise<Response> => {
  console.log("send-email function called");

  if (req.method === "OPTIONS") {
    return new Response(null, { headers: corsHeaders });
  }

  try {
    const supabaseUrl = Deno.env.get("SUPABASE_URL")!;
    const supabaseServiceKey = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!;
    const supabase = createClient(supabaseUrl, supabaseServiceKey);

    const resendApiKey = Deno.env.get("RESEND_API_KEY");
    if (!resendApiKey) {
      throw new Error("RESEND_API_KEY not configured");
    }

    const { type, to: rawTo, data }: EmailRequest = await req.json();

    // Hardcode contact recipient server-side to prevent abuse of open email relay
    const CONTACT_INBOX = "hello@vaultninecompetitions.co.uk";
    const to = type === "contact" ? CONTACT_INBOX : rawTo;

    // Auth check: only 'contact' type is allowed without authentication.
    // All other types require the service role key or an admin user.
    if (!UNAUTHENTICATED_TYPES.has(type)) {
      const authHeader = req.headers.get("Authorization");
      if (!authHeader?.startsWith("Bearer ")) {
        return new Response(JSON.stringify({ error: "Unauthorized" }), {
          status: 401,
          headers: { "Content-Type": "application/json", ...corsHeaders },
        });
      }

      const token = authHeader.replace("Bearer ", "");
      const isServiceRole = token === supabaseServiceKey;

      if (!isServiceRole) {
        // Validate as user JWT AND require admin role
        const anonKey = Deno.env.get("SUPABASE_ANON_KEY")!;
        const userClient = createClient(supabaseUrl, anonKey, {
          global: { headers: { Authorization: authHeader } },
        });
        const { data: claimsData, error: claimsError } = await userClient.auth.getUser();
        if (claimsError || !claimsData?.user) {
          return new Response(JSON.stringify({ error: "Unauthorized" }), {
            status: 401,
            headers: { "Content-Type": "application/json", ...corsHeaders },
          });
        }
        const { data: isAdmin, error: roleError } = await supabase.rpc("has_role", {
          _user_id: claimsData.user.id,
          _role: "admin",
        });
        if (roleError || !isAdmin) {
          return new Response(JSON.stringify({ error: "Forbidden" }), {
            status: 403,
            headers: { "Content-Type": "application/json", ...corsHeaders },
          });
        }
      }
    }

    console.log(`Sending ${type} email to ${to}`);


    const { subject, html } = getEmailContent(type, data);

    const fromEmail = Deno.env.get("FROM_EMAIL") || "VaultNine Competitions <noreply@vaultninecompetitions.co.uk>";

    const emailResponse = await sendEmail(to, subject, html, resendApiKey, fromEmail);

    console.log("Email sent successfully:", emailResponse);

    const { error: logError } = await supabase.from("email_logs").insert({
      recipient_email: to,
      recipient_user_id: data.userId || null,
      email_type: type,
      subject,
      status: "sent",
      metadata: data,
    });

    if (logError) {
      console.error("Error logging email:", logError);
    }

    return new Response(JSON.stringify({ success: true, data: emailResponse }), {
      status: 200,
      headers: { "Content-Type": "application/json", ...corsHeaders },
    });
  } catch (error: any) {
    console.error("Error in send-email function:", error);
    return new Response(
      JSON.stringify({ error: error.message }),
      {
        status: 500,
        headers: { "Content-Type": "application/json", ...corsHeaders },
      }
    );
  }
};

serve(handler);

````


---

## _shared/email-templates (used by auth-email-hook)

### `supabase/functions/_shared/email-templates/email-change.tsx`

````tsx
/// <reference types="npm:@types/react@18.3.1" />

import * as React from 'npm:react@18.3.1'

import {
  Body,
  Button,
  Container,
  Head,
  Heading,
  Html,
  Img,
  Link,
  Preview,
  Section,
  Text,
} from 'npm:@react-email/components@0.0.22'

interface EmailChangeEmailProps {
  siteName: string
  email: string
  newEmail: string
  confirmationUrl: string
}

export const EmailChangeEmail = ({
  siteName,
  email,
  newEmail,
  confirmationUrl,
}: EmailChangeEmailProps) => (
  <Html lang="en" dir="ltr">
    <Head />
    <Preview>Confirm your email change for VaultNine Competitions</Preview>
    <Body style={main}>
      <Container style={container}>
        <Section style={logoSection}>
          <Img
            src="https://ljxgflavyooynamjrzzt.supabase.co/storage/v1/object/public/email-assets/logo.svg"
            width="180"
            height="90"
            alt="VaultNine Competitions"
            style={logoImg}
          />
        </Section>
        <Heading style={h1}>Confirm your email change</Heading>
        <Text style={text}>
          You requested to change your VaultNine email from{' '}
          <Link href={`mailto:${email}`} style={link}>
            {email}
          </Link>{' '}
          to{' '}
          <Link href={`mailto:${newEmail}`} style={link}>
            {newEmail}
          </Link>
          .
        </Text>
        <Text style={text}>
          Click the button below to confirm this change:
        </Text>
        <Section style={buttonSection}>
          <Button style={button} href={confirmationUrl}>
            Confirm Email Change
          </Button>
        </Section>
        <Text style={footer}>
          If you didn't request this change, please secure your account immediately.
        </Text>
      </Container>
    </Body>
  </Html>
)

export default EmailChangeEmail

const main = { backgroundColor: '#ffffff', fontFamily: "'Outfit', Arial, sans-serif" }
const container = { padding: '32px 24px', maxWidth: '480px', margin: '0 auto' }
const logoSection = { textAlign: 'center' as const, marginBottom: '24px' }
const logoImg = { display: 'inline-block' as const }
const h1 = {
  fontSize: '24px',
  fontWeight: 'bold' as const,
  color: '#0F3D1F',
  margin: '0 0 16px',
  textAlign: 'center' as const,
}
const text = {
  fontSize: '15px',
  color: '#444444',
  lineHeight: '1.6',
  margin: '0 0 20px',
}
const link = { color: '#0F3D1F', textDecoration: 'underline' }
const buttonSection = { textAlign: 'center' as const, margin: '28px 0' }
const button = {
  backgroundColor: '#0F3D1F',
  color: '#F4F4F0',
  fontSize: '15px',
  fontWeight: '600' as const,
  borderRadius: '12px',
  padding: '14px 28px',
  textDecoration: 'none',
}
const footer = { fontSize: '12px', color: '#999999', margin: '32px 0 0', textAlign: 'center' as const }

````

### `supabase/functions/_shared/email-templates/invite.tsx`

````tsx
/// <reference types="npm:@types/react@18.3.1" />

import * as React from 'npm:react@18.3.1'

import {
  Body,
  Button,
  Container,
  Head,
  Heading,
  Html,
  Img,
  Link,
  Preview,
  Section,
  Text,
} from 'npm:@react-email/components@0.0.22'

interface InviteEmailProps {
  siteName: string
  siteUrl: string
  confirmationUrl: string
}

export const InviteEmail = ({
  siteName,
  siteUrl,
  confirmationUrl,
}: InviteEmailProps) => (
  <Html lang="en" dir="ltr">
    <Head />
    <Preview>You've been invited to VaultNine Competitions</Preview>
    <Body style={main}>
      <Container style={container}>
        <Section style={logoSection}>
          <Img
            src="https://ljxgflavyooynamjrzzt.supabase.co/storage/v1/object/public/email-assets/logo.svg"
            width="180"
            height="90"
            alt="VaultNine Competitions"
            style={logoImg}
          />
        </Section>
        <Heading style={h1}>You've been invited!</Heading>
        <Text style={text}>
          You've been invited to join{' '}
          <Link href={siteUrl} style={link}>
            <strong>VaultNine Competitions</strong>
          </Link>
          . Click the button below to accept and create your account.
        </Text>
        <Section style={buttonSection}>
          <Button style={button} href={confirmationUrl}>
            Accept Invitation
          </Button>
        </Section>
        <Text style={footer}>
          If you weren't expecting this invitation, you can safely ignore this email.
        </Text>
      </Container>
    </Body>
  </Html>
)

export default InviteEmail

const main = { backgroundColor: '#ffffff', fontFamily: "'Outfit', Arial, sans-serif" }
const container = { padding: '32px 24px', maxWidth: '480px', margin: '0 auto' }
const logoSection = { textAlign: 'center' as const, marginBottom: '24px' }
const logoImg = { display: 'inline-block' as const }
const h1 = {
  fontSize: '24px',
  fontWeight: 'bold' as const,
  color: '#0F3D1F',
  margin: '0 0 16px',
  textAlign: 'center' as const,
}
const text = {
  fontSize: '15px',
  color: '#444444',
  lineHeight: '1.6',
  margin: '0 0 20px',
}
const link = { color: '#0F3D1F', textDecoration: 'underline' }
const buttonSection = { textAlign: 'center' as const, margin: '28px 0' }
const button = {
  backgroundColor: '#0F3D1F',
  color: '#F4F4F0',
  fontSize: '15px',
  fontWeight: '600' as const,
  borderRadius: '12px',
  padding: '14px 28px',
  textDecoration: 'none',
}
const footer = { fontSize: '12px', color: '#999999', margin: '32px 0 0', textAlign: 'center' as const }

````

### `supabase/functions/_shared/email-templates/magic-link.tsx`

````tsx
/// <reference types="npm:@types/react@18.3.1" />

import * as React from 'npm:react@18.3.1'

import {
  Body,
  Button,
  Container,
  Head,
  Heading,
  Html,
  Img,
  Preview,
  Section,
  Text,
} from 'npm:@react-email/components@0.0.22'

interface MagicLinkEmailProps {
  siteName: string
  confirmationUrl: string
}

export const MagicLinkEmail = ({
  siteName,
  confirmationUrl,
}: MagicLinkEmailProps) => (
  <Html lang="en" dir="ltr">
    <Head />
    <Preview>Your login link for VaultNine Competitions</Preview>
    <Body style={main}>
      <Container style={container}>
        <Section style={logoSection}>
          <Img
            src="https://ljxgflavyooynamjrzzt.supabase.co/storage/v1/object/public/email-assets/logo.svg"
            width="180"
            height="90"
            alt="VaultNine Competitions"
            style={logoImg}
          />
        </Section>
        <Heading style={h1}>Your login link</Heading>
        <Text style={text}>
          Click the button below to sign in to VaultNine Competitions. This link will expire shortly.
        </Text>
        <Section style={buttonSection}>
          <Button style={button} href={confirmationUrl}>
            Sign In
          </Button>
        </Section>
        <Text style={footer}>
          If you didn't request this link, you can safely ignore this email.
        </Text>
      </Container>
    </Body>
  </Html>
)

export default MagicLinkEmail

const main = { backgroundColor: '#ffffff', fontFamily: "'Outfit', Arial, sans-serif" }
const container = { padding: '32px 24px', maxWidth: '480px', margin: '0 auto' }
const logoSection = { textAlign: 'center' as const, marginBottom: '24px' }
const logoImg = { display: 'inline-block' as const }
const h1 = {
  fontSize: '24px',
  fontWeight: 'bold' as const,
  color: '#0F3D1F',
  margin: '0 0 16px',
  textAlign: 'center' as const,
}
const text = {
  fontSize: '15px',
  color: '#444444',
  lineHeight: '1.6',
  margin: '0 0 20px',
}
const buttonSection = { textAlign: 'center' as const, margin: '28px 0' }
const button = {
  backgroundColor: '#0F3D1F',
  color: '#F4F4F0',
  fontSize: '15px',
  fontWeight: '600' as const,
  borderRadius: '12px',
  padding: '14px 28px',
  textDecoration: 'none',
}
const footer = { fontSize: '12px', color: '#999999', margin: '32px 0 0', textAlign: 'center' as const }

````

### `supabase/functions/_shared/email-templates/reauthentication.tsx`

````tsx
/// <reference types="npm:@types/react@18.3.1" />

import * as React from 'npm:react@18.3.1'

import {
  Body,
  Container,
  Head,
  Heading,
  Html,
  Img,
  Preview,
  Section,
  Text,
} from 'npm:@react-email/components@0.0.22'

interface ReauthenticationEmailProps {
  token: string
}

export const ReauthenticationEmail = ({ token }: ReauthenticationEmailProps) => (
  <Html lang="en" dir="ltr">
    <Head />
    <Preview>Your VaultNine verification code</Preview>
    <Body style={main}>
      <Container style={container}>
        <Section style={logoSection}>
          <Img
            src="https://ljxgflavyooynamjrzzt.supabase.co/storage/v1/object/public/email-assets/logo.svg"
            width="180"
            height="90"
            alt="VaultNine Competitions"
            style={logoImg}
          />
        </Section>
        <Heading style={h1}>Confirm your identity</Heading>
        <Text style={text}>Use the code below to verify it's you:</Text>
        <Text style={codeStyle}>{token}</Text>
        <Text style={footer}>
          This code will expire shortly. If you didn't request this, you can safely ignore this email.
        </Text>
      </Container>
    </Body>
  </Html>
)

export default ReauthenticationEmail

const main = { backgroundColor: '#ffffff', fontFamily: "'Outfit', Arial, sans-serif" }
const container = { padding: '32px 24px', maxWidth: '480px', margin: '0 auto' }
const logoSection = { textAlign: 'center' as const, marginBottom: '24px' }
const logoImg = { display: 'inline-block' as const }
const h1 = {
  fontSize: '24px',
  fontWeight: 'bold' as const,
  color: '#0F3D1F',
  margin: '0 0 16px',
  textAlign: 'center' as const,
}
const text = {
  fontSize: '15px',
  color: '#444444',
  lineHeight: '1.6',
  margin: '0 0 20px',
  textAlign: 'center' as const,
}
const codeStyle = {
  fontFamily: "'Outfit', Courier, monospace",
  fontSize: '28px',
  fontWeight: 'bold' as const,
  color: '#0F3D1F',
  margin: '0 0 30px',
  textAlign: 'center' as const,
  letterSpacing: '4px',
}
const footer = { fontSize: '12px', color: '#999999', margin: '32px 0 0', textAlign: 'center' as const }

````

### `supabase/functions/_shared/email-templates/recovery.tsx`

````tsx
/// <reference types="npm:@types/react@18.3.1" />

import * as React from 'npm:react@18.3.1'

import {
  Body,
  Button,
  Container,
  Head,
  Heading,
  Html,
  Img,
  Preview,
  Section,
  Text,
} from 'npm:@react-email/components@0.0.22'

interface RecoveryEmailProps {
  siteName: string
  confirmationUrl: string
}

export const RecoveryEmail = ({
  siteName,
  confirmationUrl,
}: RecoveryEmailProps) => (
  <Html lang="en" dir="ltr">
    <Head />
    <Preview>Reset your password for VaultNine Competitions</Preview>
    <Body style={main}>
      <Container style={container}>
        <Section style={logoSection}>
          <Img
            src="https://ljxgflavyooynamjrzzt.supabase.co/storage/v1/object/public/email-assets/logo.svg"
            width="180"
            height="90"
            alt="VaultNine Competitions"
            style={logoImg}
          />
        </Section>
        <Heading style={h1}>Reset your password</Heading>
        <Text style={text}>
          We received a request to reset your VaultNine password. Click the button below to choose a new one.
        </Text>
        <Section style={buttonSection}>
          <Button style={button} href={confirmationUrl}>
            Reset Password
          </Button>
        </Section>
        <Text style={footer}>
          If you didn't request a password reset, you can safely ignore this email. Your password won't be changed.
        </Text>
      </Container>
    </Body>
  </Html>
)

export default RecoveryEmail

const main = { backgroundColor: '#ffffff', fontFamily: "'Outfit', Arial, sans-serif" }
const container = { padding: '32px 24px', maxWidth: '480px', margin: '0 auto' }
const logoSection = { textAlign: 'center' as const, marginBottom: '24px' }
const logoImg = { display: 'inline-block' as const }
const h1 = {
  fontSize: '24px',
  fontWeight: 'bold' as const,
  color: '#0F3D1F',
  margin: '0 0 16px',
  textAlign: 'center' as const,
}
const text = {
  fontSize: '15px',
  color: '#444444',
  lineHeight: '1.6',
  margin: '0 0 20px',
}
const buttonSection = { textAlign: 'center' as const, margin: '28px 0' }
const button = {
  backgroundColor: '#0F3D1F',
  color: '#F4F4F0',
  fontSize: '15px',
  fontWeight: '600' as const,
  borderRadius: '12px',
  padding: '14px 28px',
  textDecoration: 'none',
}
const footer = { fontSize: '12px', color: '#999999', margin: '32px 0 0', textAlign: 'center' as const }

````

### `supabase/functions/_shared/email-templates/signup.tsx`

````tsx
/// <reference types="npm:@types/react@18.3.1" />

import * as React from 'npm:react@18.3.1'

import {
  Body,
  Button,
  Container,
  Head,
  Heading,
  Html,
  Img,
  Link,
  Preview,
  Section,
  Text,
} from 'npm:@react-email/components@0.0.22'

interface SignupEmailProps {
  siteName: string
  siteUrl: string
  recipient: string
  confirmationUrl: string
}

export const SignupEmail = ({
  siteName,
  siteUrl,
  recipient,
  confirmationUrl,
}: SignupEmailProps) => (
  <Html lang="en" dir="ltr">
    <Head />
    <Preview>Confirm your email for VaultNine Competitions</Preview>
    <Body style={main}>
      <Container style={container}>
        <Section style={logoSection}>
          <Img
            src="https://ljxgflavyooynamjrzzt.supabase.co/storage/v1/object/public/email-assets/logo.svg"
            width="180"
            height="90"
            alt="VaultNine Competitions"
            style={logoImg}
          />
        </Section>
        <Heading style={h1}>Welcome to VaultNine!</Heading>
        <Text style={text}>
          Thanks for signing up — you're one step closer to winning amazing darts prizes!
        </Text>
        <Text style={text}>
          Please confirm your email address (
          <Link href={`mailto:${recipient}`} style={link}>
            {recipient}
          </Link>
          ) by clicking the button below:
        </Text>
        <Section style={buttonSection}>
          <Button style={button} href={confirmationUrl}>
            Verify My Email
          </Button>
        </Section>
        <Text style={footer}>
          If you didn't create an account with VaultNine Competitions, you can safely ignore this email.
        </Text>
      </Container>
    </Body>
  </Html>
)

export default SignupEmail

const main = { backgroundColor: '#ffffff', fontFamily: "'Outfit', Arial, sans-serif" }
const container = { padding: '32px 24px', maxWidth: '480px', margin: '0 auto' }
const logoSection = { textAlign: 'center' as const, marginBottom: '24px' }
const logoImg = { display: 'inline-block' as const }
const h1 = {
  fontSize: '24px',
  fontWeight: 'bold' as const,
  color: '#0F3D1F',
  margin: '0 0 16px',
  textAlign: 'center' as const,
}
const text = {
  fontSize: '15px',
  color: '#444444',
  lineHeight: '1.6',
  margin: '0 0 20px',
}
const link = { color: '#0F3D1F', textDecoration: 'underline' }
const buttonSection = { textAlign: 'center' as const, margin: '28px 0' }
const button = {
  backgroundColor: '#0F3D1F',
  color: '#F4F4F0',
  fontSize: '15px',
  fontWeight: '600' as const,
  borderRadius: '12px',
  padding: '14px 28px',
  textDecoration: 'none',
}
const footer = { fontSize: '12px', color: '#999999', margin: '32px 0 0', textAlign: 'center' as const }

````
