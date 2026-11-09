# 03 — Database

Generated from the live database catalogs on export. Complete executable dump: `03-schema.sql`.

Notes:
- Backend is PostgreSQL (Supabase-hosted, via Lovable Cloud). `auth.users` is the Supabase auth table; `public.profiles` mirrors it via trigger `on_auth_user_created` → `handle_new_user()`.
- Row counts are exact `count(*)` at export time.
- Roles: enum `app_role` (`admin`,`user`) in `public.user_roles`; checked with `public.has_role()`.

## Enums

- `app_role`: admin, user

## Row counts

| Table | Rows | RLS enabled |
|---|---:|---|
| announcement_bar | 1 | yes |
| competition_spaces | 90 | yes |
| competitions | 481 | yes |
| draw_audits | 457 | yes |
| email_logs | 2807 | yes |
| entries | 18256 | yes |
| entry_tickets | 62398 | yes |
| influencers | 3 | yes |
| instant_win_prizes | 156753 | yes |
| instant_win_results | 13786 | yes |
| orders | 11468 | yes |
| postal_entries | 0 | yes |
| profiles | 2143 | yes |
| promo_codes | 31 | yes |
| site_credits | 11008 | yes |
| stats_cache | 2 | yes |
| user_limits | 10 | yes |
| user_roles | 2144 | yes |
| webhook_logs | 914 | yes |
| winners | 457 | yes |

## Tables

### announcement_bar

| Column | Type | Nullable | Default |
|---|---|---|---|
| id | uuid | NO | `gen_random_uuid()` |
| is_active | boolean | NO | `false` |
| message | text | NO | `''::text` |
| promo_code | text | YES | |
| link_url | text | YES | |
| link_text | text | YES | |
| created_at | timestamp with time zone | NO | `now()` |
| updated_at | timestamp with time zone | NO | `now()` |

Constraints (PK / FK / unique / check):

- `announcement_bar_pkey`: `PRIMARY KEY (id)`

Indexes:

```sql
CREATE UNIQUE INDEX announcement_bar_pkey ON public.announcement_bar USING btree (id);
```

RLS policies (verbatim):

```sql
CREATE POLICY "Admins can manage announcement bar" ON public.announcement_bar AS PERMISSIVE FOR ALL TO public
  USING (has_role(auth.uid(), 'admin'::app_role));
CREATE POLICY "Anyone can view announcement bar" ON public.announcement_bar AS PERMISSIVE FOR SELECT TO public
  USING (true);
```

### competition_spaces

| Column | Type | Nullable | Default |
|---|---|---|---|
| id | uuid | NO | `gen_random_uuid()` |
| competition_id | uuid | NO | |
| space_number | integer | NO | |
| player_name | text | NO | |
| status | text | NO | `'available'::text` |
| user_id | uuid | YES | |
| order_id | uuid | YES | |
| entry_id | uuid | YES | |
| reserved_at | timestamp with time zone | YES | |
| reserved_until | timestamp with time zone | YES | |
| created_at | timestamp with time zone | NO | `now()` |
| updated_at | timestamp with time zone | NO | `now()` |

Constraints (PK / FK / unique / check):

- `competition_spaces_competition_id_fkey`: `FOREIGN KEY (competition_id) REFERENCES competitions(id) ON DELETE CASCADE`
- `competition_spaces_entry_id_fkey`: `FOREIGN KEY (entry_id) REFERENCES entries(id)`
- `competition_spaces_order_id_fkey`: `FOREIGN KEY (order_id) REFERENCES orders(id)`
- `competition_spaces_pkey`: `PRIMARY KEY (id)`
- `competition_spaces_competition_id_space_number_key`: `UNIQUE (competition_id, space_number)`

Indexes:

```sql
CREATE UNIQUE INDEX competition_spaces_competition_id_space_number_key ON public.competition_spaces USING btree (competition_id, space_number);
CREATE UNIQUE INDEX competition_spaces_pkey ON public.competition_spaces USING btree (id);
```

RLS policies (verbatim):

```sql
CREATE POLICY "Admins can manage competition spaces" ON public.competition_spaces AS PERMISSIVE FOR ALL TO public
  USING (has_role(auth.uid(), 'admin'::app_role));
CREATE POLICY "Public can view non-PII space fields" ON public.competition_spaces AS PERMISSIVE FOR SELECT TO anon,authenticated
  USING (true);
```

### competitions

| Column | Type | Nullable | Default |
|---|---|---|---|
| id | uuid | NO | `gen_random_uuid()` |
| title | text | NO | |
| description | text | NO | |
| image_url | text | YES | |
| ticket_price | numeric(10,2) | NO | `0.99` |
| max_entries | integer | NO | `999` |
| entries_sold | integer | NO | `0` |
| draw_date | timestamp with time zone | NO | |
| category | text | NO | `'darts'::text` |
| status | text | NO | `'active'::text` |
| is_free | boolean | NO | `false` |
| quiz_question | text | YES | |
| quiz_options | text[] | YES | |
| quiz_answer | text | YES | |
| includes | text[] | YES | |
| created_at | timestamp with time zone | NO | `now()` |
| updated_at | timestamp with time zone | NO | `now()` |
| featured | boolean | NO | `false` |
| sale_price | numeric | YES | |
| buy_quantity | integer | YES | |
| free_quantity | integer | YES | |
| is_instant_win | boolean | NO | `false` |
| competition_type | text | NO | `'standard'::text` |
| max_entries_per_user | integer | YES | |
| exclude_from_discounts | boolean | NO | `false` |
| cost_price | numeric | YES | `0` |
| slug | text | NO | `''::text` |
| scheduled_start_at | timestamp with time zone | YES | |
| bundle_tiers | jsonb | NO | `'[]'::jsonb` |
| cash_alternative_value | numeric(10,2) | NO | `0` |
| cash_alternative_enabled | boolean | NO | `true` |
| random_ticket_allocation | boolean | NO | `true` |
| multiplier_min_tickets | integer | YES | |
| multiplier_value | integer | YES | |
| draws_on_sellout | boolean | NO | `false` |
| prize_purchased | boolean | NO | `false` |
| prize_purchased_at | timestamp with time zone | YES | |
| prize_purchased_by | uuid | YES | |
| instant_win_game | text | NO | `'classic'::text` |

Constraints (PK / FK / unique / check):

- `competitions_instant_win_game_check`: `CHECK ((instant_win_game = ANY (ARRAY['classic'::text, 'dartboard'::text, 'wheel'::text])))`
- `competitions_status_check`: `CHECK ((status = ANY (ARRAY['active'::text, 'ended'::text, 'coming-soon'::text, 'sold-out'::text, 'scheduled'::text])))`
- `competitions_pkey`: `PRIMARY KEY (id)`

Indexes:

```sql
CREATE UNIQUE INDEX competitions_pkey ON public.competitions USING btree (id);
CREATE UNIQUE INDEX competitions_slug_key ON public.competitions USING btree (slug);
CREATE INDEX idx_competitions_scheduled_start ON public.competitions USING btree (scheduled_start_at) WHERE (status = 'scheduled'::text);
CREATE INDEX idx_competitions_status ON public.competitions USING btree (status);
CREATE INDEX idx_competitions_status_draw ON public.competitions USING btree (status, draw_date);
```

RLS policies (verbatim):

```sql
CREATE POLICY "Admins can manage competitions" ON public.competitions AS PERMISSIVE FOR ALL TO public
  USING (has_role(auth.uid(), 'admin'::app_role));
CREATE POLICY "Public can view non-sensitive competition fields" ON public.competitions AS PERMISSIVE FOR SELECT TO anon,authenticated
  USING (true);
```

Triggers:

```sql
CREATE TRIGGER check_sold_out_trigger BEFORE UPDATE OF entries_sold ON public.competitions FOR EACH ROW EXECUTE FUNCTION check_competition_sold_out();
CREATE TRIGGER trg_force_sequential_instant_win BEFORE INSERT OR UPDATE ON public.competitions FOR EACH ROW EXECUTE FUNCTION force_sequential_for_instant_win();
CREATE TRIGGER trg_set_competition_slug BEFORE INSERT ON public.competitions FOR EACH ROW EXECUTE FUNCTION set_competition_slug();
CREATE TRIGGER update_competitions_updated_at BEFORE UPDATE ON public.competitions FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
```

### draw_audits

| Column | Type | Nullable | Default |
|---|---|---|---|
| id | uuid | NO | `gen_random_uuid()` |
| competition_id | uuid | NO | |
| draw_performed_at | timestamp with time zone | NO | `now()` |
| performed_by_admin_id | uuid | NO | |
| total_paid_entries | integer | NO | |
| total_postal_entries | integer | NO | |
| total_tickets | integer | NO | |
| random_seed | text | NO | |
| winning_ticket_number | integer | NO | |
| winner_type | text | NO | |
| winner_entry_id | uuid | NO | |
| winner_name | text | NO | |
| winner_email | text | NO | |
| video_timestamp | text | YES | |
| audit_hash | text | NO | |
| is_published | boolean | NO | `false` |
| created_at | timestamp with time zone | NO | `now()` |

Constraints (PK / FK / unique / check):

- `draw_audits_winner_type_check`: `CHECK ((winner_type = ANY (ARRAY['paid'::text, 'postal'::text])))`
- `draw_audits_competition_id_fkey`: `FOREIGN KEY (competition_id) REFERENCES competitions(id) ON DELETE CASCADE`
- `draw_audits_pkey`: `PRIMARY KEY (id)`

Indexes:

```sql
CREATE UNIQUE INDEX draw_audits_pkey ON public.draw_audits USING btree (id);
```

RLS policies (verbatim):

```sql
CREATE POLICY "Admins can manage draw audits" ON public.draw_audits AS PERMISSIVE FOR ALL TO public
  USING (has_role(auth.uid(), 'admin'::app_role));
```

### email_logs

| Column | Type | Nullable | Default |
|---|---|---|---|
| id | uuid | NO | `gen_random_uuid()` |
| recipient_email | text | NO | |
| recipient_user_id | uuid | YES | |
| email_type | text | NO | |
| subject | text | NO | |
| sent_at | timestamp with time zone | NO | `now()` |
| status | text | NO | `'sent'::text` |
| metadata | jsonb | YES | `'{}'::jsonb` |

Constraints (PK / FK / unique / check):

- `email_logs_recipient_user_id_fkey`: `FOREIGN KEY (recipient_user_id) REFERENCES auth.users(id) ON DELETE SET NULL`
- `email_logs_pkey`: `PRIMARY KEY (id)`

Indexes:

```sql
CREATE UNIQUE INDEX email_logs_pkey ON public.email_logs USING btree (id);
```

RLS policies (verbatim):

```sql
CREATE POLICY "Admins can manage email logs" ON public.email_logs AS PERMISSIVE FOR ALL TO public
  USING (has_role(auth.uid(), 'admin'::app_role));
CREATE POLICY "Admins can view all email logs" ON public.email_logs AS PERMISSIVE FOR SELECT TO public
  USING (has_role(auth.uid(), 'admin'::app_role));
```

### entries

| Column | Type | Nullable | Default |
|---|---|---|---|
| id | uuid | NO | `gen_random_uuid()` |
| user_id | uuid | NO | |
| competition_id | uuid | NO | |
| ticket_count | integer | NO | `1` |
| amount_paid | numeric(10,2) | NO | `0` |
| payment_status | text | NO | `'pending'::text` |
| payment_intent_id | text | YES | |
| created_at | timestamp with time zone | NO | `now()` |
| ticket_start_number | integer | YES | |

Constraints (PK / FK / unique / check):

- `entries_payment_status_check`: `CHECK ((payment_status = ANY (ARRAY['pending'::text, 'completed'::text, 'failed'::text, 'refunded'::text])))`
- `entries_competition_id_fkey`: `FOREIGN KEY (competition_id) REFERENCES competitions(id) ON DELETE CASCADE`
- `entries_user_id_fkey`: `FOREIGN KEY (user_id) REFERENCES auth.users(id) ON DELETE CASCADE`
- `entries_pkey`: `PRIMARY KEY (id)`

Indexes:

```sql
CREATE UNIQUE INDEX entries_pkey ON public.entries USING btree (id);
CREATE INDEX idx_entries_competition ON public.entries USING btree (competition_id);
CREATE INDEX idx_entries_competition_status ON public.entries USING btree (competition_id, payment_status);
CREATE INDEX idx_entries_completed_competition ON public.entries USING btree (competition_id) WHERE (payment_status = 'completed'::text);
CREATE INDEX idx_entries_completed_created_at ON public.entries USING btree (created_at DESC) WHERE (payment_status = 'completed'::text);
CREATE INDEX idx_entries_completed_user_created ON public.entries USING btree (user_id, created_at DESC) WHERE (payment_status = 'completed'::text);
CREATE UNIQUE INDEX idx_entries_unique_ticket_start ON public.entries USING btree (competition_id, ticket_start_number) WHERE ((ticket_start_number IS NOT NULL) AND (payment_status = 'completed'::text));
CREATE INDEX idx_entries_user ON public.entries USING btree (user_id);
CREATE INDEX idx_entries_user_competition ON public.entries USING btree (user_id, competition_id);
```

RLS policies (verbatim):

```sql
CREATE POLICY "Admins can manage all entries" ON public.entries AS PERMISSIVE FOR ALL TO public
  USING (has_role(auth.uid(), 'admin'::app_role));
CREATE POLICY "Admins can view all entries" ON public.entries AS PERMISSIVE FOR SELECT TO public
  USING (has_role(auth.uid(), 'admin'::app_role));
CREATE POLICY "Users can view their own entries" ON public.entries AS PERMISSIVE FOR SELECT TO public
  USING ((auth.uid() = user_id));
```

Triggers:

```sql
CREATE TRIGGER aa_shared_entry_limit BEFORE INSERT OR UPDATE ON public.entries FOR EACH ROW EXECUTE FUNCTION enforce_shared_entry_limit();
CREATE TRIGGER assign_ticket_numbers_trigger BEFORE INSERT OR UPDATE ON public.entries FOR EACH ROW EXECUTE FUNCTION assign_ticket_numbers();
CREATE TRIGGER on_entry_payment_completed AFTER INSERT OR UPDATE OF payment_status ON public.entries FOR EACH ROW EXECUTE FUNCTION handle_entry_payment_completed();
CREATE TRIGGER trg_allocate_random_tickets AFTER INSERT OR UPDATE ON public.entries FOR EACH ROW EXECUTE FUNCTION allocate_random_tickets_for_entry();
CREATE TRIGGER trg_assign_ticket_numbers BEFORE INSERT OR UPDATE OF payment_status ON public.entries FOR EACH ROW EXECUTE FUNCTION assign_ticket_numbers();
CREATE TRIGGER trg_enforce_one_free_entry_per_user BEFORE INSERT ON public.entries FOR EACH ROW EXECUTE FUNCTION enforce_one_free_entry_per_user();
```

### entry_tickets

| Column | Type | Nullable | Default |
|---|---|---|---|
| id | uuid | NO | `gen_random_uuid()` |
| competition_id | uuid | NO | |
| entry_id | uuid | YES | |
| postal_entry_id | uuid | YES | |
| user_id | uuid | YES | |
| ticket_number | integer | NO | |
| created_at | timestamp with time zone | NO | `now()` |

Constraints (PK / FK / unique / check):

- `entry_tickets_source_chk`: `CHECK (((entry_id IS NOT NULL) OR (postal_entry_id IS NOT NULL)))`
- `entry_tickets_competition_id_fkey`: `FOREIGN KEY (competition_id) REFERENCES competitions(id) ON DELETE CASCADE`
- `entry_tickets_entry_id_fkey`: `FOREIGN KEY (entry_id) REFERENCES entries(id) ON DELETE CASCADE`
- `entry_tickets_postal_entry_id_fkey`: `FOREIGN KEY (postal_entry_id) REFERENCES postal_entries(id) ON DELETE CASCADE`
- `entry_tickets_pkey`: `PRIMARY KEY (id)`
- `entry_tickets_unique_number`: `UNIQUE (competition_id, ticket_number)`

Indexes:

```sql
CREATE UNIQUE INDEX entry_tickets_pkey ON public.entry_tickets USING btree (id);
CREATE UNIQUE INDEX entry_tickets_unique_number ON public.entry_tickets USING btree (competition_id, ticket_number);
CREATE INDEX idx_entry_tickets_comp_number ON public.entry_tickets USING btree (competition_id, ticket_number);
CREATE INDEX idx_entry_tickets_entry ON public.entry_tickets USING btree (entry_id);
CREATE INDEX idx_entry_tickets_postal ON public.entry_tickets USING btree (postal_entry_id);
CREATE INDEX idx_entry_tickets_user ON public.entry_tickets USING btree (user_id);
```

RLS policies (verbatim):

```sql
CREATE POLICY "Admins can view all tickets" ON public.entry_tickets AS PERMISSIVE FOR SELECT TO authenticated
  USING (has_role(auth.uid(), 'admin'::app_role));
CREATE POLICY "Users can view their own tickets" ON public.entry_tickets AS PERMISSIVE FOR SELECT TO authenticated
  USING ((user_id = auth.uid()));
```

### influencers

| Column | Type | Nullable | Default |
|---|---|---|---|
| id | uuid | NO | `gen_random_uuid()` |
| name | text | NO | |
| email | text | YES | |
| social_handle | text | YES | |
| commission_rate | numeric | NO | `10` |
| commission_type | text | NO | `'percentage'::text` |
| promo_code_id | uuid | YES | |
| total_sales | numeric | NO | `0` |
| total_commission | numeric | NO | `0` |
| is_active | boolean | NO | `true` |
| notes | text | YES | |
| created_at | timestamp with time zone | NO | `now()` |
| updated_at | timestamp with time zone | NO | `now()` |

Constraints (PK / FK / unique / check):

- `influencers_promo_code_id_fkey`: `FOREIGN KEY (promo_code_id) REFERENCES promo_codes(id)`
- `influencers_pkey`: `PRIMARY KEY (id)`

Indexes:

```sql
CREATE UNIQUE INDEX influencers_pkey ON public.influencers USING btree (id);
```

RLS policies (verbatim):

```sql
CREATE POLICY "Admins can manage influencers" ON public.influencers AS PERMISSIVE FOR ALL TO public
  USING (has_role(auth.uid(), 'admin'::app_role));
```

Triggers:

```sql
CREATE TRIGGER update_influencers_updated_at BEFORE UPDATE ON public.influencers FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
```

### instant_win_prizes

| Column | Type | Nullable | Default |
|---|---|---|---|
| id | uuid | NO | `gen_random_uuid()` |
| competition_id | uuid | NO | |
| prize_name | text | NO | |
| prize_value | numeric | NO | `0` |
| total_quantity | integer | NO | `1` |
| claimed_quantity | integer | NO | `0` |
| prize_tier | integer | NO | `1` |
| created_at | timestamp with time zone | NO | `now()` |
| prize_type | text | NO | `'physical'::text` |
| winning_ticket_number | integer | YES | |

Constraints (PK / FK / unique / check):

- `instant_win_prizes_competition_id_fkey`: `FOREIGN KEY (competition_id) REFERENCES competitions(id) ON DELETE CASCADE`
- `instant_win_prizes_pkey`: `PRIMARY KEY (id)`

Indexes:

```sql
CREATE INDEX idx_iwp_competition ON public.instant_win_prizes USING btree (competition_id);
CREATE INDEX idx_iwp_competition_ticket ON public.instant_win_prizes USING btree (competition_id, winning_ticket_number) WHERE (winning_ticket_number IS NOT NULL);
CREATE INDEX idx_iwp_competition_tier ON public.instant_win_prizes USING btree (competition_id, prize_tier);
CREATE UNIQUE INDEX instant_win_prizes_pkey ON public.instant_win_prizes USING btree (id);
```

RLS policies (verbatim):

```sql
CREATE POLICY "Admins can manage instant win prizes" ON public.instant_win_prizes AS PERMISSIVE FOR ALL TO public
  USING (has_role(auth.uid(), 'admin'::app_role));
CREATE POLICY "Public can view non-sensitive instant win prize fields" ON public.instant_win_prizes AS PERMISSIVE FOR SELECT TO anon,authenticated
  USING (true);
```

### instant_win_results

| Column | Type | Nullable | Default |
|---|---|---|---|
| id | uuid | NO | `gen_random_uuid()` |
| competition_id | uuid | NO | |
| entry_id | uuid | NO | |
| user_id | uuid | NO | |
| prize_id | uuid | YES | |
| is_winner | boolean | NO | `false` |
| revealed | boolean | NO | `false` |
| created_at | timestamp with time zone | NO | `now()` |
| fulfillment_status | text | NO | `'pending'::text` |

Constraints (PK / FK / unique / check):

- `instant_win_results_competition_id_fkey`: `FOREIGN KEY (competition_id) REFERENCES competitions(id) ON DELETE CASCADE`
- `instant_win_results_entry_id_fkey`: `FOREIGN KEY (entry_id) REFERENCES entries(id) ON DELETE CASCADE`
- `instant_win_results_prize_id_fkey`: `FOREIGN KEY (prize_id) REFERENCES instant_win_prizes(id)`
- `instant_win_results_pkey`: `PRIMARY KEY (id)`

Indexes:

```sql
CREATE INDEX idx_iw_results_winner_prize ON public.instant_win_results USING btree (competition_id, prize_id) WHERE (is_winner = true);
CREATE INDEX idx_iwr_competition ON public.instant_win_results USING btree (competition_id);
CREATE INDEX idx_iwr_entry ON public.instant_win_results USING btree (entry_id);
CREATE INDEX idx_iwr_prize ON public.instant_win_results USING btree (prize_id) WHERE (prize_id IS NOT NULL);
CREATE INDEX idx_iwr_user ON public.instant_win_results USING btree (user_id);
CREATE INDEX idx_iwr_user_winner ON public.instant_win_results USING btree (user_id) WHERE (is_winner = true);
CREATE INDEX idx_iwr_winner_created_at ON public.instant_win_results USING btree (created_at DESC) WHERE (is_winner = true);
CREATE UNIQUE INDEX instant_win_results_pkey ON public.instant_win_results USING btree (id);
```

RLS policies (verbatim):

```sql
CREATE POLICY "Admins can manage instant win results" ON public.instant_win_results AS PERMISSIVE FOR ALL TO public
  USING (has_role(auth.uid(), 'admin'::app_role));
CREATE POLICY "Users can view their own results" ON public.instant_win_results AS PERMISSIVE FOR SELECT TO public
  USING ((auth.uid() = user_id));
```

Triggers:

```sql
CREATE TRIGGER guard_instant_win_results_update_trg BEFORE UPDATE ON public.instant_win_results FOR EACH ROW EXECUTE FUNCTION guard_instant_win_results_update();
```

### orders

| Column | Type | Nullable | Default |
|---|---|---|---|
| id | uuid | NO | `gen_random_uuid()` |
| order_number | text | NO | |
| user_id | uuid | NO | |
| amount | numeric | NO | |
| currency | text | NO | `'GBP'::text` |
| status | text | NO | `'created'::text` |
| cashflows_payment_job_reference | text | YES | |
| cashflows_payment_reference | text | YES | |
| cashflows_last_status | text | YES | |
| cashflows_action_url | text | YES | |
| entry_ids | text[] | YES | `'{}'::text[]` |
| competition_ids | text[] | YES | `'{}'::text[]` |
| metadata | jsonb | YES | `'{}'::jsonb` |
| created_at | timestamp with time zone | NO | `now()` |
| updated_at | timestamp with time zone | NO | `now()` |
| promo_code | text | YES | |
| influencer_id | uuid | YES | |

Constraints (PK / FK / unique / check):

- `orders_influencer_id_fkey`: `FOREIGN KEY (influencer_id) REFERENCES influencers(id)`
- `orders_pkey`: `PRIMARY KEY (id)`
- `orders_order_number_key`: `UNIQUE (order_number)`

Indexes:

```sql
CREATE INDEX idx_orders_cashflows_ref ON public.orders USING btree (cashflows_payment_job_reference);
CREATE INDEX idx_orders_order_number ON public.orders USING btree (order_number);
CREATE INDEX idx_orders_status_created_at ON public.orders USING btree (status, created_at DESC);
CREATE INDEX idx_orders_user_id ON public.orders USING btree (user_id);
CREATE INDEX idx_orders_user_status_created ON public.orders USING btree (user_id, status, created_at DESC);
CREATE UNIQUE INDEX orders_order_number_key ON public.orders USING btree (order_number);
CREATE UNIQUE INDEX orders_pkey ON public.orders USING btree (id);
```

RLS policies (verbatim):

```sql
CREATE POLICY "Admins can manage all orders" ON public.orders AS PERMISSIVE FOR ALL TO public
  USING (has_role(auth.uid(), 'admin'::app_role));
CREATE POLICY "Admins can view all orders" ON public.orders AS PERMISSIVE FOR SELECT TO public
  USING (has_role(auth.uid(), 'admin'::app_role));
CREATE POLICY "Users can view their own orders" ON public.orders AS PERMISSIVE FOR SELECT TO public
  USING ((auth.uid() = user_id));
```

Triggers:

```sql
CREATE TRIGGER aa_shared_order_limit BEFORE INSERT OR UPDATE ON public.orders FOR EACH ROW EXECUTE FUNCTION enforce_order_entry_limit();
CREATE TRIGGER update_orders_updated_at BEFORE UPDATE ON public.orders FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
```

### postal_entries

| Column | Type | Nullable | Default |
|---|---|---|---|
| id | uuid | NO | `gen_random_uuid()` |
| competition_id | uuid | NO | |
| full_name | text | NO | |
| email | text | NO | |
| address_line1 | text | NO | |
| address_line2 | text | YES | |
| city | text | NO | |
| postcode | text | NO | |
| country | text | NO | `'United Kingdom'::text` |
| ticket_count | integer | NO | `1` |
| created_at | timestamp with time zone | NO | `now()` |
| added_by_admin_id | uuid | NO | |
| notes | text | YES | |
| ticket_start_number | integer | YES | |

Constraints (PK / FK / unique / check):

- `postal_entries_competition_id_fkey`: `FOREIGN KEY (competition_id) REFERENCES competitions(id) ON DELETE CASCADE`
- `postal_entries_pkey`: `PRIMARY KEY (id)`

Indexes:

```sql
CREATE INDEX idx_postal_competition ON public.postal_entries USING btree (competition_id);
CREATE UNIQUE INDEX postal_entries_pkey ON public.postal_entries USING btree (id);
```

RLS policies (verbatim):

```sql
CREATE POLICY "Admins can manage postal entries" ON public.postal_entries AS PERMISSIVE FOR ALL TO public
  USING (has_role(auth.uid(), 'admin'::app_role));
```

Triggers:

```sql
CREATE TRIGGER aa_shared_postal_limit BEFORE INSERT OR UPDATE ON public.postal_entries FOR EACH ROW EXECUTE FUNCTION enforce_shared_entry_limit();
CREATE TRIGGER ab_assign_postal_ticket_numbers BEFORE INSERT ON public.postal_entries FOR EACH ROW EXECUTE FUNCTION assign_postal_ticket_numbers();
CREATE TRIGGER trg_allocate_random_tickets_postal AFTER INSERT ON public.postal_entries FOR EACH ROW EXECUTE FUNCTION allocate_random_tickets_for_postal();
```

### profiles

| Column | Type | Nullable | Default |
|---|---|---|---|
| id | uuid | NO | `gen_random_uuid()` |
| user_id | uuid | NO | |
| email | text | NO | |
| full_name | text | YES | |
| avatar_url | text | YES | |
| created_at | timestamp with time zone | NO | `now()` |
| updated_at | timestamp with time zone | NO | `now()` |
| marketing_opt_in | boolean | NO | `false` |
| phone | text | YES | |
| address_line1 | text | YES | |
| address_line2 | text | YES | |
| city | text | YES | |
| postcode | text | YES | |
| country | text | YES | `'United Kingdom'::text` |

Constraints (PK / FK / unique / check):

- `profiles_user_id_fkey`: `FOREIGN KEY (user_id) REFERENCES auth.users(id) ON DELETE CASCADE`
- `profiles_pkey`: `PRIMARY KEY (id)`
- `profiles_user_id_key`: `UNIQUE (user_id)`

Indexes:

```sql
CREATE INDEX idx_profiles_created_at ON public.profiles USING btree (created_at DESC);
CREATE UNIQUE INDEX profiles_pkey ON public.profiles USING btree (id);
CREATE UNIQUE INDEX profiles_user_id_key ON public.profiles USING btree (user_id);
```

RLS policies (verbatim):

```sql
CREATE POLICY "Admins can update all profiles" ON public.profiles AS PERMISSIVE FOR UPDATE TO public
  USING (has_role(auth.uid(), 'admin'::app_role));
CREATE POLICY "Admins can view all profiles" ON public.profiles AS PERMISSIVE FOR SELECT TO public
  USING (has_role(auth.uid(), 'admin'::app_role));
CREATE POLICY "Users can update their own profile" ON public.profiles AS PERMISSIVE FOR UPDATE TO public
  USING ((auth.uid() = user_id));
CREATE POLICY "Users can view their own profile" ON public.profiles AS PERMISSIVE FOR SELECT TO public
  USING ((auth.uid() = user_id));
```

Triggers:

```sql
CREATE TRIGGER update_profiles_updated_at BEFORE UPDATE ON public.profiles FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
```

### promo_codes

| Column | Type | Nullable | Default |
|---|---|---|---|
| id | uuid | NO | `gen_random_uuid()` |
| code | text | NO | |
| discount_type | text | NO | `'percentage'::text` |
| discount_value | numeric | NO | |
| max_uses | integer | YES | |
| times_used | integer | NO | `0` |
| min_order_amount | numeric | YES | `0` |
| valid_from | timestamp with time zone | NO | `now()` |
| valid_until | timestamp with time zone | YES | |
| is_active | boolean | NO | `true` |
| created_at | timestamp with time zone | NO | `now()` |
| updated_at | timestamp with time zone | NO | `now()` |
| excluded_competition_ids | text[] | YES | `'{}'::text[]` |

Constraints (PK / FK / unique / check):

- `promo_codes_pkey`: `PRIMARY KEY (id)`
- `unique_promo_code`: `UNIQUE (code)`

Indexes:

```sql
CREATE UNIQUE INDEX promo_codes_pkey ON public.promo_codes USING btree (id);
CREATE UNIQUE INDEX unique_promo_code ON public.promo_codes USING btree (code);
```

RLS policies (verbatim):

```sql
CREATE POLICY "Admins can manage promo codes" ON public.promo_codes AS PERMISSIVE FOR ALL TO public
  USING (has_role(auth.uid(), 'admin'::app_role));
```

Triggers:

```sql
CREATE TRIGGER update_promo_codes_updated_at BEFORE UPDATE ON public.promo_codes FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
```

### site_credits

| Column | Type | Nullable | Default |
|---|---|---|---|
| id | uuid | NO | `gen_random_uuid()` |
| user_id | uuid | NO | |
| amount | numeric | NO | |
| type | text | NO | `'grant'::text` |
| reason | text | YES | |
| order_id | uuid | YES | |
| granted_by | uuid | YES | |
| expires_at | timestamp with time zone | YES | |
| created_at | timestamp with time zone | NO | `now()` |

Constraints (PK / FK / unique / check):

- `site_credits_order_id_fkey`: `FOREIGN KEY (order_id) REFERENCES orders(id)`
- `site_credits_pkey`: `PRIMARY KEY (id)`

Indexes:

```sql
CREATE INDEX idx_site_credits_expires_at ON public.site_credits USING btree (expires_at);
CREATE INDEX idx_site_credits_user_id ON public.site_credits USING btree (user_id);
CREATE INDEX idx_site_credits_user_type ON public.site_credits USING btree (user_id, type);
CREATE UNIQUE INDEX site_credits_pkey ON public.site_credits USING btree (id);
```

RLS policies (verbatim):

```sql
CREATE POLICY "Admins can manage all site credits" ON public.site_credits AS PERMISSIVE FOR ALL TO public
  USING (has_role(auth.uid(), 'admin'::app_role));
CREATE POLICY "Users can view their own credits" ON public.site_credits AS PERMISSIVE FOR SELECT TO public
  USING ((auth.uid() = user_id));
```

### stats_cache

| Column | Type | Nullable | Default |
|---|---|---|---|
| key | text | NO | |
| value | jsonb | NO | |
| refreshed_at | timestamp with time zone | NO | `now()` |

Constraints (PK / FK / unique / check):

- `stats_cache_pkey`: `PRIMARY KEY (key)`

Indexes:

```sql
CREATE UNIQUE INDEX stats_cache_pkey ON public.stats_cache USING btree (key);
```

RLS policies (verbatim):

_None — table is fully locked to anon/authenticated (service role / security-definer only)._

### user_limits

| Column | Type | Nullable | Default |
|---|---|---|---|
| user_id | uuid | NO | |
| daily_limit | numeric | YES | |
| weekly_limit | numeric | YES | |
| monthly_limit | numeric | YES | |
| cool_off_until | timestamp with time zone | YES | |
| self_excluded_until | timestamp with time zone | YES | |
| created_at | timestamp with time zone | NO | `now()` |
| updated_at | timestamp with time zone | NO | `now()` |

Constraints (PK / FK / unique / check):

- `user_limits_pkey`: `PRIMARY KEY (user_id)`

Indexes:

```sql
CREATE UNIQUE INDEX user_limits_pkey ON public.user_limits USING btree (user_id);
```

RLS policies (verbatim):

```sql
CREATE POLICY "Admins manage all limits" ON public.user_limits AS PERMISSIVE FOR ALL TO public
  USING (has_role(auth.uid(), 'admin'::app_role));
CREATE POLICY "Users insert own limits" ON public.user_limits AS PERMISSIVE FOR INSERT TO authenticated
  WITH CHECK ((auth.uid() = user_id));
CREATE POLICY "Users update own limits" ON public.user_limits AS PERMISSIVE FOR UPDATE TO authenticated
  USING ((auth.uid() = user_id))
  WITH CHECK ((auth.uid() = user_id));
CREATE POLICY "Users view own limits" ON public.user_limits AS PERMISSIVE FOR SELECT TO authenticated
  USING ((auth.uid() = user_id));
```

Triggers:

```sql
CREATE TRIGGER guard_user_limits_update BEFORE UPDATE ON public.user_limits FOR EACH ROW EXECUTE FUNCTION guard_user_limits_update();
CREATE TRIGGER update_user_limits_updated_at BEFORE UPDATE ON public.user_limits FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
```

### user_roles

| Column | Type | Nullable | Default |
|---|---|---|---|
| id | uuid | NO | `gen_random_uuid()` |
| user_id | uuid | NO | |
| role | app_role | NO | `'user'::app_role` |
| created_at | timestamp with time zone | NO | `now()` |

Constraints (PK / FK / unique / check):

- `user_roles_user_id_fkey`: `FOREIGN KEY (user_id) REFERENCES auth.users(id) ON DELETE CASCADE`
- `user_roles_pkey`: `PRIMARY KEY (id)`
- `user_roles_user_id_role_key`: `UNIQUE (user_id, role)`

Indexes:

```sql
CREATE UNIQUE INDEX user_roles_pkey ON public.user_roles USING btree (id);
CREATE UNIQUE INDEX user_roles_user_id_role_key ON public.user_roles USING btree (user_id, role);
```

RLS policies (verbatim):

```sql
CREATE POLICY "Admins can manage roles" ON public.user_roles AS PERMISSIVE FOR ALL TO authenticated
  USING (has_role(auth.uid(), 'admin'::app_role))
  WITH CHECK (has_role(auth.uid(), 'admin'::app_role));
CREATE POLICY "Admins can view all roles" ON public.user_roles AS PERMISSIVE FOR SELECT TO public
  USING (has_role(auth.uid(), 'admin'::app_role));
CREATE POLICY "Users can view their own roles" ON public.user_roles AS PERMISSIVE FOR SELECT TO public
  USING ((auth.uid() = user_id));
```

### webhook_logs

| Column | Type | Nullable | Default |
|---|---|---|---|
| id | uuid | NO | `gen_random_uuid()` |
| source | text | NO | `'cashflows'::text` |
| event_type | text | YES | |
| payload | jsonb | YES | |
| processing_result | text | YES | |
| processing_error | text | YES | |
| created_at | timestamp with time zone | NO | `now()` |

Constraints (PK / FK / unique / check):

- `webhook_logs_pkey`: `PRIMARY KEY (id)`

Indexes:

```sql
CREATE INDEX idx_webhook_logs_errors_created ON public.webhook_logs USING btree (created_at DESC) WHERE (processing_error IS NOT NULL);
CREATE INDEX idx_webhook_logs_source ON public.webhook_logs USING btree (source);
CREATE UNIQUE INDEX webhook_logs_pkey ON public.webhook_logs USING btree (id);
```

RLS policies (verbatim):

```sql
CREATE POLICY "Admins can manage webhook logs" ON public.webhook_logs AS PERMISSIVE FOR ALL TO public
  USING (has_role(auth.uid(), 'admin'::app_role));
CREATE POLICY "Admins can view webhook logs" ON public.webhook_logs AS PERMISSIVE FOR SELECT TO public
  USING (has_role(auth.uid(), 'admin'::app_role));
```

### winners

| Column | Type | Nullable | Default |
|---|---|---|---|
| id | uuid | NO | `gen_random_uuid()` |
| user_id | uuid | YES | |
| competition_id | uuid | NO | |
| winner_name | text | NO | |
| prize_description | text | NO | |
| won_at | timestamp with time zone | NO | `now()` |
| announced | boolean | NO | `false` |
| created_at | timestamp with time zone | NO | `now()` |
| verification_status | text | NO | `'pending'::text` |
| id_document_url | text | YES | |
| id_document_uploaded_at | timestamp with time zone | YES | |
| verified_at | timestamp with time zone | YES | |
| verified_by_admin_id | uuid | YES | |
| verification_notes | text | YES | |
| prize_dispatched | boolean | NO | `false` |
| prize_dispatched_at | timestamp with time zone | YES | |
| winner_image_url | text | YES | |

Constraints (PK / FK / unique / check):

- `winners_verification_status_check`: `CHECK ((verification_status = ANY (ARRAY['pending'::text, 'id_uploaded'::text, 'approved'::text, 'rejected'::text])))`
- `winners_competition_id_fkey`: `FOREIGN KEY (competition_id) REFERENCES competitions(id) ON DELETE CASCADE`
- `winners_user_id_fkey`: `FOREIGN KEY (user_id) REFERENCES auth.users(id) ON DELETE SET NULL`
- `winners_pkey`: `PRIMARY KEY (id)`

Indexes:

```sql
CREATE INDEX idx_winners_announced ON public.winners USING btree (competition_id) WHERE (announced = true);
CREATE INDEX idx_winners_competition ON public.winners USING btree (competition_id);
CREATE INDEX idx_winners_user ON public.winners USING btree (user_id);
CREATE UNIQUE INDEX winners_pkey ON public.winners USING btree (id);
```

RLS policies (verbatim):

```sql
CREATE POLICY "Admins can manage winners" ON public.winners AS PERMISSIVE FOR ALL TO public
  USING (has_role(auth.uid(), 'admin'::app_role));
CREATE POLICY "Admins can view all winners" ON public.winners AS PERMISSIVE FOR SELECT TO public
  USING (has_role(auth.uid(), 'admin'::app_role));
CREATE POLICY "Public can view announced winners (non-sensitive)" ON public.winners AS PERMISSIVE FOR SELECT TO anon,authenticated
  USING ((announced = true));
CREATE POLICY "Winners can update their own records for verification" ON public.winners AS PERMISSIVE FOR UPDATE TO public
  USING ((auth.uid() = user_id))
  WITH CHECK ((auth.uid() = user_id));
```

Triggers:

```sql
CREATE TRIGGER guard_winners_update_trg BEFORE UPDATE ON public.winners FOR EACH ROW EXECUTE FUNCTION guard_winners_update();
```

## Views (verbatim)

### competitions_public

Options: `security_invoker=on`

```sql
CREATE OR REPLACE VIEW public.competitions_public AS
 SELECT id,
    title,
    description,
    image_url,
    ticket_price,
    max_entries,
    entries_sold,
    draw_date,
    category,
    status,
    is_free,
    quiz_question,
    quiz_options,
    includes,
    created_at,
    updated_at,
    featured,
    sale_price,
    competition_type,
    is_instant_win,
    free_quantity,
    buy_quantity,
    exclude_from_discounts,
    max_entries_per_user,
    slug,
    bundle_tiers,
    cash_alternative_value,
    cash_alternative_enabled,
    multiplier_min_tickets,
    multiplier_value,
    draws_on_sellout
   FROM competitions;
```

### draw_audits_public

Options: `security_invoker=on`

```sql
CREATE OR REPLACE VIEW public.draw_audits_public AS
 SELECT id,
    competition_id,
    draw_performed_at,
    total_paid_entries,
    total_postal_entries,
    total_tickets,
    winning_ticket_number,
    winner_name,
    winner_type,
    winner_entry_id,
    audit_hash,
    random_seed,
    video_timestamp,
    is_published,
    created_at,
    performed_by_admin_id
   FROM draw_audits
  WHERE is_published = true;
```

### public_winners

Options: `security_barrier=true,security_invoker=true`

```sql
CREATE OR REPLACE VIEW public.public_winners AS
 SELECT id,
    winner_name,
    prize_description,
    won_at,
    announced,
    competition_id,
    winner_image_url
   FROM winners
  WHERE announced = true;
```

## Functions (verbatim)

### admin_competition_report

```sql
CREATE OR REPLACE FUNCTION public.admin_competition_report()
 RETURNS TABLE(competition_id text, cash_revenue numeric, entry_count bigint, credit_used numeric, prize_cost_credit numeric, prize_cost_physical numeric)
 LANGUAGE plpgsql
 SECURITY DEFINER
 SET search_path TO 'public'
AS $function$
DECLARE
  v_cached jsonb;
BEGIN
  IF NOT public.has_role(auth.uid(), 'admin'::app_role) THEN
    RAISE EXCEPTION 'Not authorized';
  END IF;

  SELECT value INTO v_cached
  FROM stats_cache
  WHERE key = 'admin_competition_report' AND refreshed_at > now() - interval '5 minutes';

  IF v_cached IS NULL THEN
    WITH cash AS (
      SELECT e.competition_id::text AS cid, SUM(e.amount_paid) AS rev, COUNT(*)::bigint AS cnt
      FROM entries e
      WHERE e.payment_status = 'completed'
      GROUP BY e.competition_id
    ),
    credit_orders AS (
      SELECT o.id,
             COALESCE((o.metadata->>'creditUsed')::numeric, 0) AS credit_amt,
             o.competition_ids,
             o.metadata->'items' AS items
      FROM orders o
      WHERE o.status IN ('paid','completed')
        AND COALESCE((o.metadata->>'creditUsed')::numeric, 0) > 0
        AND o.competition_ids IS NOT NULL
        AND array_length(o.competition_ids, 1) > 0
    ),
    credit_alloc AS (
      SELECT item->>'competitionId' AS cid,
             co.credit_amt / jsonb_array_length(co.items) AS share
      FROM credit_orders co
      CROSS JOIN LATERAL jsonb_array_elements(co.items) AS item
      WHERE jsonb_typeof(co.items) = 'array'
        AND jsonb_array_length(co.items) > 0
        AND item->>'competitionId' IS NOT NULL
      UNION ALL
      SELECT cid_un AS cid,
             co.credit_amt / array_length(co.competition_ids, 1) AS share
      FROM credit_orders co
      CROSS JOIN LATERAL unnest(co.competition_ids) AS cid_un
      WHERE co.items IS NULL
         OR jsonb_typeof(co.items) <> 'array'
         OR jsonb_array_length(co.items) = 0
    ),
    credit AS (
      SELECT ca.cid, SUM(ca.share) AS credit_used
      FROM credit_alloc ca
      GROUP BY ca.cid
    ),
    prizes AS (
      SELECT r.competition_id::text AS cid,
             SUM(CASE WHEN p.prize_type = 'site_credit' THEN p.prize_value ELSE 0 END) AS pz_credit,
             SUM(CASE WHEN p.prize_type <> 'site_credit' THEN p.prize_value ELSE 0 END) AS pz_physical
      FROM instant_win_results r
      JOIN instant_win_prizes p ON p.id = r.prize_id
      WHERE r.is_winner = true
        AND p.prize_value > 0
        AND LOWER(p.prize_name) <> 'no prize'
      GROUP BY r.competition_id
    ),
    report_rows AS (
      SELECT c.id::text AS competition_id,
             COALESCE(cash.rev, 0) AS cash_revenue,
             COALESCE(cash.cnt, 0) AS entry_count,
             COALESCE(credit.credit_used, 0) AS credit_used,
             COALESCE(prizes.pz_credit, 0) AS prize_cost_credit,
             COALESCE(prizes.pz_physical, 0) AS prize_cost_physical
      FROM competitions c
      LEFT JOIN cash ON cash.cid = c.id::text
      LEFT JOIN credit ON credit.cid = c.id::text
      LEFT JOIN prizes ON prizes.cid = c.id::text
    )
    SELECT COALESCE(jsonb_agg(to_jsonb(report_rows)), '[]'::jsonb) INTO v_cached FROM report_rows;

    INSERT INTO stats_cache (key, value, refreshed_at)
    VALUES ('admin_competition_report', v_cached, now())
    ON CONFLICT (key) DO UPDATE SET value = EXCLUDED.value, refreshed_at = EXCLUDED.refreshed_at;
  END IF;

  RETURN QUERY
  SELECT (r->>'competition_id')::text,
         (r->>'cash_revenue')::numeric,
         (r->>'entry_count')::bigint,
         (r->>'credit_used')::numeric,
         (r->>'prize_cost_credit')::numeric,
         (r->>'prize_cost_physical')::numeric
  FROM jsonb_array_elements(v_cached) AS r;
END;
$function$
```

### admin_credit_health

```sql
CREATE OR REPLACE FUNCTION public.admin_credit_health()
 RETURNS jsonb
 LANGUAGE plpgsql
 STABLE SECURITY DEFINER
 SET search_path TO 'public'
AS $function$
DECLARE
  v_issued numeric;
  v_redeemed numeric;
  v_active_grants numeric;
  v_monthly jsonb;
BEGIN
  IF NOT public.has_role(auth.uid(), 'admin'::app_role) THEN
    RAISE EXCEPTION 'Not authorized';
  END IF;

  SELECT COALESCE(SUM(amount) FILTER (WHERE type = 'grant'), 0),
         COALESCE(SUM(amount) FILTER (WHERE type = 'deduction'), 0),
         COALESCE(SUM(amount) FILTER (WHERE type = 'grant' AND (expires_at IS NULL OR expires_at > now())), 0)
  INTO v_issued, v_redeemed, v_active_grants
  FROM site_credits;

  SELECT COALESCE(jsonb_agg(jsonb_build_object(
           'month', to_char(m.month, 'YYYY-MM-DD'),
           'issued', m.issued,
           'redeemed', m.redeemed
         ) ORDER BY m.month), '[]'::jsonb)
  INTO v_monthly
  FROM (
    SELECT date_trunc('month', created_at) AS month,
           COALESCE(SUM(amount) FILTER (WHERE type = 'grant'), 0) AS issued,
           COALESCE(SUM(amount) FILTER (WHERE type = 'deduction'), 0) AS redeemed
    FROM site_credits
    GROUP BY 1
  ) m;

  RETURN jsonb_build_object(
    'total_issued', v_issued,
    'total_redeemed', v_redeemed,
    'total_outstanding', GREATEST(0, v_active_grants - v_redeemed),
    'monthly', v_monthly
  );
END;
$function$
```

### admin_get_competition

```sql
CREATE OR REPLACE FUNCTION public.admin_get_competition(p_id uuid)
 RETURNS SETOF competitions
 LANGUAGE plpgsql
 STABLE SECURITY DEFINER
 SET search_path TO 'public'
AS $function$
BEGIN
  IF NOT public.has_role(auth.uid(), 'admin'::app_role) THEN
    RAISE EXCEPTION 'Not authorized';
  END IF;
  RETURN QUERY SELECT * FROM public.competitions WHERE id = p_id;
END;
$function$
```

### admin_get_competition_spaces

```sql
CREATE OR REPLACE FUNCTION public.admin_get_competition_spaces(p_competition_id uuid)
 RETURNS SETOF competition_spaces
 LANGUAGE plpgsql
 STABLE SECURITY DEFINER
 SET search_path TO 'public'
AS $function$
BEGIN
  IF NOT public.has_role(auth.uid(), 'admin'::app_role) THEN
    RAISE EXCEPTION 'Not authorized';
  END IF;
  RETURN QUERY
    SELECT * FROM public.competition_spaces
    WHERE competition_id = p_competition_id
    ORDER BY space_number;
END;
$function$
```

### admin_get_instant_win_prizes

```sql
CREATE OR REPLACE FUNCTION public.admin_get_instant_win_prizes(p_competition_id uuid)
 RETURNS SETOF instant_win_prizes
 LANGUAGE plpgsql
 STABLE SECURITY DEFINER
 SET search_path TO 'public'
AS $function$
BEGIN
  IF NOT public.has_role(auth.uid(), 'admin'::app_role) THEN
    RAISE EXCEPTION 'Not authorized';
  END IF;
  RETURN QUERY
    SELECT * FROM public.instant_win_prizes
    WHERE competition_id = p_competition_id
    ORDER BY prize_tier;
END;
$function$
```

### admin_list_competitions_full

```sql
CREATE OR REPLACE FUNCTION public.admin_list_competitions_full()
 RETURNS SETOF competitions
 LANGUAGE plpgsql
 STABLE SECURITY DEFINER
 SET search_path TO 'public'
AS $function$
BEGIN
  IF NOT public.has_role(auth.uid(), 'admin'::app_role) THEN
    RAISE EXCEPTION 'Not authorized';
  END IF;
  RETURN QUERY SELECT * FROM public.competitions;
END;
$function$
```

### admin_list_user_winners

```sql
CREATE OR REPLACE FUNCTION public.admin_list_user_winners(p_user_id uuid)
 RETURNS SETOF winners
 LANGUAGE plpgsql
 STABLE SECURITY DEFINER
 SET search_path TO 'public'
AS $function$
BEGIN
  IF NOT public.has_role(auth.uid(), 'admin'::app_role) THEN
    RAISE EXCEPTION 'Not authorized';
  END IF;
  RETURN QUERY SELECT * FROM public.winners WHERE user_id = p_user_id ORDER BY won_at DESC;
END;
$function$
```

### admin_list_winners_full

```sql
CREATE OR REPLACE FUNCTION public.admin_list_winners_full()
 RETURNS SETOF winners
 LANGUAGE plpgsql
 STABLE SECURITY DEFINER
 SET search_path TO 'public'
AS $function$
BEGIN
  IF NOT public.has_role(auth.uid(), 'admin'::app_role) THEN
    RAISE EXCEPTION 'Not authorized';
  END IF;
  RETURN QUERY SELECT * FROM public.winners ORDER BY won_at DESC;
END;
$function$
```

### allocate_random_tickets

```sql
CREATE OR REPLACE FUNCTION public.allocate_random_tickets(p_competition_id uuid, p_entry_id uuid, p_postal_entry_id uuid, p_user_id uuid, p_count integer)
 RETURNS integer
 LANGUAGE plpgsql
 SECURITY DEFINER
 SET search_path TO 'public'
AS $function$
DECLARE
  v_max integer;
  v_taken integer;
  v_inserted integer := 0;
  v_batch integer;
  v_attempt integer := 0;
BEGIN
  IF p_count IS NULL OR p_count <= 0 THEN
    RETURN 0;
  END IF;

  -- Serialise allocation for this competition
  SELECT max_entries INTO v_max FROM competitions WHERE id = p_competition_id FOR UPDATE;
  IF v_max IS NULL OR v_max <= 0 THEN
    RAISE EXCEPTION 'Competition % has no ticket pool', p_competition_id;
  END IF;

  SELECT COUNT(*) INTO v_taken FROM entry_tickets WHERE competition_id = p_competition_id;
  IF v_taken + p_count > v_max THEN
    RAISE EXCEPTION 'Not enough tickets remaining in competition %', p_competition_id;
  END IF;

  -- Fast path: random probing (cheap while the pool is not near-full)
  WHILE v_inserted < p_count AND v_attempt < 40 LOOP
    v_attempt := v_attempt + 1;

    INSERT INTO entry_tickets (competition_id, entry_id, postal_entry_id, user_id, ticket_number)
    SELECT p_competition_id, p_entry_id, p_postal_entry_id, p_user_id, x.n
    FROM (
      SELECT c.n
      FROM (
        SELECT DISTINCT 1 + floor(random() * v_max)::int AS n
        FROM generate_series(1, (p_count - v_inserted) * 3 + 5)
      ) c
      WHERE NOT EXISTS (
        SELECT 1 FROM entry_tickets t
        WHERE t.competition_id = p_competition_id AND t.ticket_number = c.n
      )
      LIMIT (p_count - v_inserted)
    ) x
    ON CONFLICT (competition_id, ticket_number) DO NOTHING;

    GET DIAGNOSTICS v_batch = ROW_COUNT;
    v_inserted := v_inserted + v_batch;
  END LOOP;

  -- Fallback: exhaustive shuffle of the remaining pool
  IF v_inserted < p_count THEN
    INSERT INTO entry_tickets (competition_id, entry_id, postal_entry_id, user_id, ticket_number)
    SELECT p_competition_id, p_entry_id, p_postal_entry_id, p_user_id, g.n
    FROM generate_series(1, v_max) AS g(n)
    WHERE NOT EXISTS (
      SELECT 1 FROM entry_tickets t
      WHERE t.competition_id = p_competition_id AND t.ticket_number = g.n
    )
    ORDER BY random()
    LIMIT (p_count - v_inserted)
    ON CONFLICT (competition_id, ticket_number) DO NOTHING;

    GET DIAGNOSTICS v_batch = ROW_COUNT;
    v_inserted := v_inserted + v_batch;
  END IF;

  RETURN v_inserted;
END;
$function$
```

### allocate_random_tickets_for_entry

```sql
CREATE OR REPLACE FUNCTION public.allocate_random_tickets_for_entry()
 RETURNS trigger
 LANGUAGE plpgsql
 SECURITY DEFINER
 SET search_path TO 'public'
AS $function$
BEGIN
  IF NEW.payment_status = 'completed'
     AND (TG_OP = 'INSERT' OR OLD.payment_status IS DISTINCT FROM 'completed') THEN
    IF (SELECT COALESCE(random_ticket_allocation, false) FROM competitions WHERE id = NEW.competition_id) THEN
      IF NOT EXISTS (SELECT 1 FROM entry_tickets WHERE entry_id = NEW.id) THEN
        PERFORM public.allocate_random_tickets(NEW.competition_id, NEW.id, NULL, NEW.user_id, NEW.ticket_count);
      END IF;
    END IF;
  END IF;
  RETURN NEW;
END;
$function$
```

### allocate_random_tickets_for_postal

```sql
CREATE OR REPLACE FUNCTION public.allocate_random_tickets_for_postal()
 RETURNS trigger
 LANGUAGE plpgsql
 SECURITY DEFINER
 SET search_path TO 'public'
AS $function$
BEGIN
  IF (SELECT COALESCE(random_ticket_allocation, false) FROM competitions WHERE id = NEW.competition_id) THEN
    IF NOT EXISTS (SELECT 1 FROM entry_tickets WHERE postal_entry_id = NEW.id) THEN
      PERFORM public.allocate_random_tickets(NEW.competition_id, NULL, NEW.id, NULL, NEW.ticket_count);
    END IF;
  END IF;
  RETURN NEW;
END;
$function$
```

### assign_postal_ticket_numbers

```sql
CREATE OR REPLACE FUNCTION public.assign_postal_ticket_numbers()
 RETURNS trigger
 LANGUAGE plpgsql
 SECURITY DEFINER
 SET search_path TO 'public'
AS $function$
DECLARE v_paid_end integer; v_postal_end integer; v_legacy integer; BEGIN
 PERFORM 1 FROM public.competitions WHERE id=NEW.competition_id FOR UPDATE;
 IF (SELECT COALESCE(random_ticket_allocation,false) FROM public.competitions WHERE id=NEW.competition_id) THEN RETURN NEW; END IF;
 SELECT COALESCE(MAX(ticket_start_number+ticket_count-1),0) INTO v_paid_end FROM public.entries WHERE competition_id=NEW.competition_id AND payment_status='completed';
 SELECT COALESCE(MAX(ticket_start_number+ticket_count-1),0),COALESCE(SUM(ticket_count) FILTER(WHERE ticket_start_number IS NULL),0) INTO v_postal_end,v_legacy FROM public.postal_entries WHERE competition_id=NEW.competition_id;
 NEW.ticket_start_number:=GREATEST(v_paid_end+v_legacy,v_postal_end)+1;
 RETURN NEW;
END $function$
```

### assign_ticket_numbers

```sql
CREATE OR REPLACE FUNCTION public.assign_ticket_numbers()
 RETURNS trigger
 LANGUAGE plpgsql
 SECURITY DEFINER
 SET search_path TO 'public'
AS $function$
DECLARE v_end integer; v_postal_end integer; BEGIN
 IF NEW.payment_status='completed' AND NEW.ticket_start_number IS NULL THEN
  PERFORM 1 FROM public.competitions WHERE id=NEW.competition_id FOR UPDATE;
  IF (SELECT COALESCE(random_ticket_allocation,false) FROM public.competitions WHERE id=NEW.competition_id) THEN RETURN NEW; END IF;
  SELECT COALESCE(MAX(ticket_start_number+ticket_count-1),0) INTO v_end FROM public.entries WHERE competition_id=NEW.competition_id AND payment_status='completed' AND id<>NEW.id;
  SELECT GREATEST(COALESCE(MAX(ticket_start_number+ticket_count-1),0),COALESCE(SUM(ticket_count),0)) INTO v_postal_end FROM public.postal_entries WHERE competition_id=NEW.competition_id;
  NEW.ticket_start_number:=GREATEST(v_end,v_postal_end)+1;
 END IF;
 RETURN NEW;
END $function$
```

### check_competition_sold_out

```sql
CREATE OR REPLACE FUNCTION public.check_competition_sold_out()
 RETURNS trigger
 LANGUAGE plpgsql
 SECURITY DEFINER
 SET search_path TO 'public'
AS $function$
BEGIN
  -- Check if entries_sold equals or exceeds max_entries
  IF NEW.entries_sold >= NEW.max_entries AND NEW.status = 'active' THEN
    NEW.status := 'sold-out';
  END IF;
  RETURN NEW;
END;
$function$
```

### check_user_spend_limit

```sql
CREATE OR REPLACE FUNCTION public.check_user_spend_limit(p_user_id uuid, p_amount numeric)
 RETURNS jsonb
 LANGUAGE plpgsql
 STABLE SECURITY DEFINER
 SET search_path TO 'public'
AS $function$
DECLARE
  v_limits record;
  v_day numeric;
  v_week numeric;
  v_month numeric;
BEGIN
  IF auth.uid() IS NOT NULL AND auth.uid() IS DISTINCT FROM p_user_id AND NOT public.has_role(auth.uid(), 'admin'::app_role) THEN
    RAISE EXCEPTION 'Not authorized';
  END IF;

  SELECT * INTO v_limits FROM user_limits WHERE user_id = p_user_id;

  IF NOT FOUND THEN
    RETURN jsonb_build_object('allowed', true);
  END IF;

  IF v_limits.self_excluded_until IS NOT NULL AND v_limits.self_excluded_until > now() THEN
    RETURN jsonb_build_object('allowed', false, 'reason', 'self_excluded',
      'message', 'You have self-excluded from purchases until ' || to_char(v_limits.self_excluded_until, 'DD Mon YYYY') || '.',
      'until', v_limits.self_excluded_until);
  END IF;

  IF v_limits.cool_off_until IS NOT NULL AND v_limits.cool_off_until > now() THEN
    RETURN jsonb_build_object('allowed', false, 'reason', 'cool_off',
      'message', 'You are in a cool-off period until ' || to_char(v_limits.cool_off_until, 'DD Mon YYYY HH24:MI') || '.',
      'until', v_limits.cool_off_until);
  END IF;

  SELECT COALESCE(SUM(amount), 0) INTO v_day FROM orders WHERE user_id = p_user_id AND status = 'paid' AND created_at >= now() - interval '1 day';
  SELECT COALESCE(SUM(amount), 0) INTO v_week FROM orders WHERE user_id = p_user_id AND status = 'paid' AND created_at >= now() - interval '7 days';
  SELECT COALESCE(SUM(amount), 0) INTO v_month FROM orders WHERE user_id = p_user_id AND status = 'paid' AND created_at >= now() - interval '30 days';

  IF v_limits.daily_limit IS NOT NULL AND (v_day + p_amount) > v_limits.daily_limit THEN
    RETURN jsonb_build_object('allowed', false, 'reason', 'daily_limit',
      'message', 'This purchase would exceed your daily spend limit of £' || v_limits.daily_limit::text || '. You have spent £' || round(v_day,2)::text || ' in the last 24 hours.',
      'limit', v_limits.daily_limit, 'spent', v_day);
  END IF;
  IF v_limits.weekly_limit IS NOT NULL AND (v_week + p_amount) > v_limits.weekly_limit THEN
    RETURN jsonb_build_object('allowed', false, 'reason', 'weekly_limit',
      'message', 'This purchase would exceed your weekly spend limit of £' || v_limits.weekly_limit::text || '. You have spent £' || round(v_week,2)::text || ' in the last 7 days.',
      'limit', v_limits.weekly_limit, 'spent', v_week);
  END IF;
  IF v_limits.monthly_limit IS NOT NULL AND (v_month + p_amount) > v_limits.monthly_limit THEN
    RETURN jsonb_build_object('allowed', false, 'reason', 'monthly_limit',
      'message', 'This purchase would exceed your monthly spend limit of £' || v_limits.monthly_limit::text || '. You have spent £' || round(v_month,2)::text || ' in the last 30 days.',
      'limit', v_limits.monthly_limit, 'spent', v_month);
  END IF;

  RETURN jsonb_build_object('allowed', true);
END;
$function$
```

### competition_person_entry_count

```sql
CREATE OR REPLACE FUNCTION public.competition_person_entry_count(p_comp uuid, p_user uuid, p_email text, p_exclude_entry uuid DEFAULT NULL::uuid, p_exclude_postal uuid DEFAULT NULL::uuid)
 RETURNS integer
 LANGUAGE sql
 STABLE SECURITY DEFINER
 SET search_path TO 'public'
AS $function$
 SELECT (COALESCE((SELECT SUM(e.ticket_count) FROM public.entries e WHERE e.competition_id=p_comp AND e.payment_status='completed' AND e.id IS DISTINCT FROM p_exclude_entry AND (e.user_id=p_user OR e.user_id IN (SELECT user_id FROM public.profiles WHERE lower(trim(email))=lower(trim(p_email))))),0)
 + COALESCE((SELECT SUM(p.ticket_count) FROM public.postal_entries p WHERE p.competition_id=p_comp AND p.id IS DISTINCT FROM p_exclude_postal AND lower(trim(p.email))=lower(trim(p_email))),0))::integer
$function$
```

### competition_person_reserved_count

```sql
CREATE OR REPLACE FUNCTION public.competition_person_reserved_count(p_comp uuid, p_user uuid, p_email text, p_exclude_order uuid DEFAULT NULL::uuid)
 RETURNS integer
 LANGUAGE sql
 STABLE SECURITY DEFINER
 SET search_path TO 'public'
AS $function$
 SELECT COALESCE(SUM(GREATEST(0,
 CASE WHEN jsonb_typeof(o.metadata->'items')='array' THEN
   COALESCE((SELECT SUM(COALESCE((i->>'totalTickets')::integer,(i->>'ticketCount')::integer,0)) FROM jsonb_array_elements(o.metadata->'items') i WHERE i->>'competitionId'=p_comp::text),0)
 ELSE CASE WHEN o.metadata->>'competitionId'=p_comp::text THEN COALESCE(jsonb_array_length(o.metadata->'spaceIds'),0) ELSE 0 END END
 - COALESCE((SELECT SUM(e.ticket_count) FROM public.entries e WHERE e.competition_id=p_comp AND e.user_id=o.user_id AND e.payment_status='completed' AND (e.id::text=ANY(COALESCE(o.entry_ids,ARRAY[]::text[])) OR (e.payment_intent_id IS NOT NULL AND e.payment_intent_id=o.cashflows_payment_reference))),0)
 )),0)::integer
 FROM public.orders o WHERE o.id IS DISTINCT FROM p_exclude_order AND (o.user_id=p_user OR o.user_id IN (SELECT user_id FROM public.profiles WHERE lower(trim(email))=lower(trim(p_email))))
 AND ((o.status='pending_payment' AND o.created_at>now()-interval '30 minutes') OR (o.status='paid' AND cardinality(COALESCE(o.entry_ids,ARRAY[]::text[]))=0 AND o.created_at>now()-interval '30 minutes'))
$function$
```

### consume_promo_code

```sql
CREATE OR REPLACE FUNCTION public.consume_promo_code(p_code text)
 RETURNS jsonb
 LANGUAGE plpgsql
 SECURITY DEFINER
 SET search_path TO 'public'
AS $function$
DECLARE
  v_promo record;
BEGIN
  SELECT * INTO v_promo
  FROM promo_codes
  WHERE UPPER(code) = UPPER(p_code) AND is_active = true
  FOR UPDATE;

  IF NOT FOUND THEN
    RETURN jsonb_build_object('success', false, 'error', 'Invalid code');
  END IF;

  IF v_promo.valid_until IS NOT NULL AND v_promo.valid_until < now() THEN
    RETURN jsonb_build_object('success', false, 'error', 'Code expired');
  END IF;

  IF v_promo.max_uses IS NOT NULL AND v_promo.times_used >= v_promo.max_uses THEN
    RETURN jsonb_build_object('success', false, 'error', 'Code usage limit reached');
  END IF;

  UPDATE promo_codes SET times_used = times_used + 1, updated_at = now()
  WHERE id = v_promo.id;

  RETURN jsonb_build_object('success', true, 'promo_id', v_promo.id);
END;
$function$
```

### deduct_site_credit

```sql
CREATE OR REPLACE FUNCTION public.deduct_site_credit(p_user_id uuid, p_amount numeric, p_reason text, p_order_id uuid)
 RETURNS numeric
 LANGUAGE plpgsql
 SECURITY DEFINER
 SET search_path TO 'public'
AS $function$
DECLARE
  v_available numeric;
  v_deduct numeric;
  v_caller uuid;
BEGIN
  v_caller := auth.uid();
  IF v_caller IS NOT NULL
     AND v_caller IS DISTINCT FROM p_user_id
     AND NOT public.has_role(v_caller, 'admin'::app_role) THEN
    RAISE EXCEPTION 'Not authorized';
  END IF;

  -- Lock user's credit rows to prevent concurrent reads
  PERFORM 1 FROM site_credits WHERE user_id = p_user_id FOR UPDATE;

  -- Get actual available balance
  v_available := public.get_user_credit_balance(p_user_id);

  -- Cap deduction to available balance
  v_deduct := LEAST(p_amount, v_available);
  v_deduct := ROUND(v_deduct, 2);

  IF v_deduct <= 0 THEN
    RETURN 0;
  END IF;

  -- Check idempotency: skip if deduction already exists for this order
  IF p_order_id IS NOT NULL AND EXISTS (
    SELECT 1 FROM site_credits
    WHERE user_id = p_user_id AND type = 'deduction' AND order_id = p_order_id
  ) THEN
    RETURN 0;
  END IF;

  INSERT INTO site_credits (user_id, amount, type, reason, order_id)
  VALUES (p_user_id, v_deduct, 'deduction', p_reason, p_order_id);

  RETURN v_deduct;
END;
$function$
```

### enforce_one_free_entry_per_user

```sql
CREATE OR REPLACE FUNCTION public.enforce_one_free_entry_per_user()
 RETURNS trigger
 LANGUAGE plpgsql
 SECURITY DEFINER
 SET search_path TO 'public'
AS $function$
BEGIN
 -- Deprecated single-entry restriction; aa_shared_entry_limit now enforces 50.
 RETURN NEW;
END $function$
```

### enforce_order_entry_limit

```sql
CREATE OR REPLACE FUNCTION public.enforce_order_entry_limit()
 RETURNS trigger
 LANGUAGE plpgsql
 SECURITY DEFINER
 SET search_path TO 'public'
AS $function$
DECLARE v_comp uuid; v_requested integer;
BEGIN
 IF NEW.status<>'pending_payment' THEN RETURN NEW; END IF;
 IF TG_OP='UPDATE' AND OLD.user_id=NEW.user_id AND OLD.metadata IS NOT DISTINCT FROM NEW.metadata AND OLD.status='pending_payment' THEN RETURN NEW; END IF;
 FOR v_comp IN SELECT DISTINCT x::uuid FROM unnest(NEW.competition_ids) x ORDER BY x::uuid LOOP
   PERFORM 1 FROM public.competitions WHERE id=v_comp FOR UPDATE;
   IF jsonb_typeof(NEW.metadata->'items')='array' THEN
     SELECT COALESCE(SUM(COALESCE((i->>'totalTickets')::integer,(i->>'ticketCount')::integer,0)),0) INTO v_requested FROM jsonb_array_elements(NEW.metadata->'items') i WHERE i->>'competitionId'=v_comp::text;
   ELSE v_requested:=COALESCE(jsonb_array_length(NEW.metadata->'spaceIds'),0); END IF;
   IF v_requested<1 THEN RAISE EXCEPTION 'Entry count must be positive' USING ERRCODE='23514'; END IF;
 END LOOP;
 RETURN NEW;
END $function$
```

### enforce_shared_entry_limit

```sql
CREATE OR REPLACE FUNCTION public.enforce_shared_entry_limit()
 RETURNS trigger
 LANGUAGE plpgsql
 SECURITY DEFINER
 SET search_path TO 'public'
AS $function$
BEGIN
 IF TG_TABLE_NAME='entries' THEN
   IF NEW.payment_status IS DISTINCT FROM 'completed' THEN RETURN NEW; END IF;
   IF NEW.ticket_count IS NULL OR NEW.ticket_count<1 THEN RAISE EXCEPTION 'Entry count must be positive' USING ERRCODE='23514'; END IF;
 ELSE
   IF NEW.ticket_count IS DISTINCT FROM 1 THEN RAISE EXCEPTION 'One postal entry per envelope; log each envelope separately' USING ERRCODE='23514'; END IF;
   IF COALESCE(trim(NEW.email),'')='' THEN RAISE EXCEPTION 'Postal entry email is required'; END IF;
 END IF;
 PERFORM 1 FROM public.competitions WHERE id=NEW.competition_id FOR UPDATE;
 RETURN NEW;
END $function$
```

### enter_free_competition

```sql
CREATE OR REPLACE FUNCTION public.enter_free_competition(p_competition_id uuid, p_ticket_count integer DEFAULT 1)
 RETURNS uuid
 LANGUAGE plpgsql
 SECURITY DEFINER
 SET search_path TO 'public'
AS $function$
DECLARE v_user uuid:=auth.uid(); v_comp record; v_entry uuid; BEGIN
 IF v_user IS NULL THEN RAISE EXCEPTION 'Please sign in to enter'; END IF;
 IF p_ticket_count IS NULL OR p_ticket_count<1 THEN RAISE EXCEPTION 'Choose at least one entry'; END IF;
 SELECT * INTO v_comp FROM public.competitions WHERE id=p_competition_id FOR UPDATE;
 IF NOT FOUND THEN RAISE EXCEPTION 'Competition not found'; END IF;
 IF NOT v_comp.is_free OR COALESCE(v_comp.ticket_price,0)<>0 THEN RAISE EXCEPTION 'This competition requires payment'; END IF;
 IF v_comp.status<>'active' THEN RAISE EXCEPTION 'This competition is not open for entries'; END IF;
 IF NOT COALESCE(v_comp.draws_on_sellout,false) AND v_comp.draw_date<=now() THEN RAISE EXCEPTION 'Entries for this competition have closed'; END IF;
 INSERT INTO public.entries(user_id,competition_id,ticket_count,amount_paid,payment_status)
 VALUES(v_user,p_competition_id,p_ticket_count,0,'completed') RETURNING id INTO v_entry;
 RETURN v_entry;
END $function$
```

### estimate_payment_fee

```sql
CREATE OR REPLACE FUNCTION public.estimate_payment_fee(p_amount numeric)
 RETURNS numeric
 LANGUAGE sql
 IMMUTABLE
 SET search_path TO 'public'
AS $function$
  SELECT CASE WHEN COALESCE(p_amount,0) <= 0 THEN 0
              ELSE ROUND((p_amount * 0.019 + 0.20)::numeric, 2) END
$function$
```

### expire_space_reservations

```sql
CREATE OR REPLACE FUNCTION public.expire_space_reservations()
 RETURNS integer
 LANGUAGE plpgsql
 SECURITY DEFINER
 SET search_path TO 'public'
AS $function$
DECLARE v_count integer;
BEGIN
  UPDATE competition_spaces
  SET status = 'available', user_id = NULL, order_id = NULL, reserved_at = NULL, reserved_until = NULL, updated_at = now()
  WHERE status = 'reserved' AND reserved_until < now();
  GET DIAGNOSTICS v_count = ROW_COUNT;
  RETURN v_count;
END;
$function$
```

### force_sequential_for_instant_win

```sql
CREATE OR REPLACE FUNCTION public.force_sequential_for_instant_win()
 RETURNS trigger
 LANGUAGE plpgsql
 SET search_path TO 'public'
AS $function$
BEGIN
  IF NEW.is_instant_win THEN
    NEW.random_ticket_allocation := false;
  END IF;
  RETURN NEW;
END; $function$
```

### generate_unique_competition_slug

```sql
CREATE OR REPLACE FUNCTION public.generate_unique_competition_slug(p_title text, p_id uuid)
 RETURNS text
 LANGUAGE plpgsql
 SET search_path TO 'public'
AS $function$
DECLARE
  v_base text;
  v_slug text;
  v_n integer := 1;
BEGIN
  v_base := public.slugify(p_title);
  IF v_base = '' OR v_base IS NULL THEN
    v_base := 'competition';
  END IF;
  v_slug := v_base;
  WHILE EXISTS (SELECT 1 FROM public.competitions WHERE slug = v_slug AND (p_id IS NULL OR id <> p_id)) LOOP
    v_n := v_n + 1;
    v_slug := v_base || '-' || v_n;
  END LOOP;
  RETURN v_slug;
END;
$function$
```

### get_announced_draw_results

```sql
CREATE OR REPLACE FUNCTION public.get_announced_draw_results()
 RETURNS TABLE(competition_id uuid, winning_ticket_number integer, total_tickets integer)
 LANGUAGE sql
 STABLE SECURITY DEFINER
 SET search_path TO 'public'
AS $function$
  SELECT DISTINCT ON (da.competition_id)
    da.competition_id,
    da.winning_ticket_number,
    da.total_tickets
  FROM public.draw_audits da
  WHERE EXISTS (
    SELECT 1 FROM public.winners w
    WHERE w.competition_id = da.competition_id AND w.announced = true
  )
  ORDER BY da.competition_id, da.draw_performed_at DESC;
$function$
```

### get_instant_win_prize_status

```sql
CREATE OR REPLACE FUNCTION public.get_instant_win_prize_status(p_competition_id uuid)
 RETURNS TABLE(group_key text, prize_name text, prize_value numeric, prize_tier integer, prize_type text, total_quantity integer, claimed_quantity integer, winners jsonb)
 LANGUAGE sql
 STABLE SECURITY DEFINER
 SET search_path TO 'public'
AS $function$
  WITH slots AS (
    SELECT
      p.id AS prize_id,
      p.prize_name,
      p.prize_value,
      p.prize_tier,
      p.prize_type,
      p.winning_ticket_number,
      r.id IS NOT NULL AS is_claimed,
      r.created_at AS won_at,
      pr.full_name AS winner_full_name
    FROM public.instant_win_prizes p
    LEFT JOIN public.instant_win_results r
      ON r.prize_id = p.id AND r.is_winner = true
    LEFT JOIN public.profiles pr
      ON pr.user_id = r.user_id
    WHERE p.competition_id = p_competition_id
      AND lower(p.prize_name) <> 'no prize'
  ),
  named AS (
    SELECT
      s.*,
      lower(btrim(regexp_replace(regexp_replace(s.prize_name, '\s*#\d+\s*$', ''), '\s*\(\d+\)\s*$', ''))) AS gkey,
      btrim(regexp_replace(regexp_replace(s.prize_name, '\s*#\d+\s*$', ''), '\s*\(\d+\)\s*$', '')) AS display_name
    FROM slots s
  )
  SELECT
    n.gkey AS group_key,
    MIN(n.display_name) AS prize_name,
    MAX(n.prize_value) AS prize_value,
    MIN(n.prize_tier) AS prize_tier,
    MIN(n.prize_type) AS prize_type,
    COUNT(*)::int AS total_quantity,
    COUNT(*) FILTER (WHERE n.is_claimed)::int AS claimed_quantity,
    COALESCE(
      jsonb_agg(
        jsonb_build_object(
          'winning_ticket_number', n.winning_ticket_number,
          'winner_display_name',
            CASE
              WHEN n.winner_full_name IS NULL OR btrim(n.winner_full_name) = '' THEN 'Winner'
              ELSE
                split_part(btrim(n.winner_full_name), ' ', 1)
                || CASE
                     WHEN position(' ' in btrim(n.winner_full_name)) > 0
                       THEN ' ' || left(split_part(btrim(n.winner_full_name), ' ', 2), 1) || '.'
                     ELSE ''
                   END
            END,
          'won_at', n.won_at
        )
        ORDER BY n.won_at DESC NULLS LAST
      ) FILTER (WHERE n.is_claimed),
      '[]'::jsonb
    ) AS winners
  FROM named n
  GROUP BY n.gkey
  ORDER BY MIN(n.prize_tier) ASC, MAX(n.prize_value) DESC;
$function$
```

### get_my_competition_entry_count

```sql
CREATE OR REPLACE FUNCTION public.get_my_competition_entry_count(p_competition_id uuid)
 RETURNS integer
 LANGUAGE plpgsql
 STABLE SECURITY DEFINER
 SET search_path TO 'public'
AS $function$
DECLARE v_email text; BEGIN
 IF auth.uid() IS NULL THEN RAISE EXCEPTION 'Please sign in'; END IF;
 SELECT email INTO v_email FROM public.profiles WHERE user_id=auth.uid();
 RETURN public.competition_person_entry_count(p_competition_id,auth.uid(),v_email);
END $function$
```

### get_my_competition_spaces

```sql
CREATE OR REPLACE FUNCTION public.get_my_competition_spaces()
 RETURNS SETOF competition_spaces
 LANGUAGE plpgsql
 STABLE SECURITY DEFINER
 SET search_path TO 'public'
AS $function$
BEGIN
  IF auth.uid() IS NULL THEN
    RETURN;
  END IF;
  RETURN QUERY
    SELECT * FROM public.competition_spaces
    WHERE user_id = auth.uid()
    ORDER BY created_at DESC;
END;
$function$
```

### get_my_winner_records

```sql
CREATE OR REPLACE FUNCTION public.get_my_winner_records()
 RETURNS TABLE(id uuid, competition_id uuid, winner_name text, prize_description text, verification_status text, id_document_url text, verified_at timestamp with time zone, prize_dispatched boolean, won_at timestamp with time zone, created_at timestamp with time zone)
 LANGUAGE sql
 STABLE SECURITY DEFINER
 SET search_path TO 'public'
AS $function$
  SELECT
    w.id,
    w.competition_id,
    w.winner_name,
    w.prize_description,
    w.verification_status,
    w.id_document_url,
    w.verified_at,
    w.prize_dispatched,
    w.won_at,
    w.created_at
  FROM public.winners w
  WHERE w.user_id = auth.uid()
  ORDER BY w.created_at DESC;
$function$
```

### get_platform_stats

```sql
CREATE OR REPLACE FUNCTION public.get_platform_stats()
 RETURNS jsonb
 LANGUAGE plpgsql
 SECURITY DEFINER
 SET search_path TO 'public'
AS $function$
DECLARE
  v_cached jsonb;
  v_unique_winners integer;
  v_paid_tickets integer;
  v_postal_tickets integer;
  v_members integer;
  v_instant_prize_value numeric;
  v_main_prize_value numeric;
  v_result jsonb;
BEGIN
  SELECT value INTO v_cached
  FROM stats_cache
  WHERE key = 'platform_stats' AND refreshed_at > now() - interval '15 minutes';

  IF v_cached IS NOT NULL THEN
    RETURN v_cached;
  END IF;

  SELECT COUNT(DISTINCT user_id) INTO v_unique_winners
  FROM (
    SELECT user_id FROM winners WHERE user_id IS NOT NULL
    UNION
    SELECT user_id FROM instant_win_results WHERE is_winner = true AND user_id IS NOT NULL
  ) AS combined;

  SELECT COALESCE(SUM(ticket_count), 0) INTO v_paid_tickets FROM entries WHERE payment_status = 'completed';
  SELECT COALESCE(SUM(ticket_count), 0) INTO v_postal_tickets FROM postal_entries;
  SELECT COUNT(*) INTO v_members FROM profiles;

  SELECT COALESCE(SUM(p.prize_value * iwr.is_winner_count), 0) INTO v_instant_prize_value
  FROM (
    SELECT prize_id, COUNT(*) AS is_winner_count
    FROM instant_win_results
    WHERE is_winner = true AND prize_id IS NOT NULL
    GROUP BY prize_id
  ) iwr
  JOIN instant_win_prizes p ON p.id = iwr.prize_id;

  SELECT COALESCE(SUM(c.ticket_price * c.max_entries), 0) INTO v_main_prize_value
  FROM competitions c
  WHERE EXISTS (SELECT 1 FROM winners w WHERE w.competition_id = c.id);

  v_result := jsonb_build_object(
    'winners', v_unique_winners,
    'tickets', v_paid_tickets + v_postal_tickets,
    'members', v_members,
    'prize_value', v_instant_prize_value + v_main_prize_value
  );

  INSERT INTO stats_cache (key, value, refreshed_at)
  VALUES ('platform_stats', v_result, now())
  ON CONFLICT (key) DO UPDATE SET value = EXCLUDED.value, refreshed_at = EXCLUDED.refreshed_at;

  RETURN v_result;
END;
$function$
```

### get_user_credit_balance

```sql
CREATE OR REPLACE FUNCTION public.get_user_credit_balance(p_user_id uuid)
 RETURNS numeric
 LANGUAGE plpgsql
 STABLE SECURITY DEFINER
 SET search_path TO 'public'
AS $function$
DECLARE
  v_total_grants numeric;
  v_total_deductions numeric;
  v_expired_grants numeric;
  v_active_grants numeric;
  v_consumed_from_expired numeric;
BEGIN
  IF auth.uid() IS NOT NULL AND auth.uid() IS DISTINCT FROM p_user_id AND NOT public.has_role(auth.uid(), 'admin'::app_role) THEN
    RAISE EXCEPTION 'Not authorized';
  END IF;

  SELECT COALESCE(SUM(amount), 0) INTO v_total_grants
  FROM site_credits WHERE user_id = p_user_id AND type = 'grant';

  SELECT COALESCE(SUM(amount), 0) INTO v_total_deductions
  FROM site_credits WHERE user_id = p_user_id AND type = 'deduction';

  SELECT COALESCE(SUM(amount), 0) INTO v_expired_grants
  FROM site_credits WHERE user_id = p_user_id AND type = 'grant'
  AND expires_at IS NOT NULL AND expires_at <= now();

  v_active_grants := v_total_grants - v_expired_grants;
  v_consumed_from_expired := LEAST(v_total_deductions, v_expired_grants);

  RETURN GREATEST(0, v_active_grants - (v_total_deductions - v_consumed_from_expired));
END;
$function$
```

### get_user_spend_summary

```sql
CREATE OR REPLACE FUNCTION public.get_user_spend_summary(p_user_id uuid)
 RETURNS jsonb
 LANGUAGE plpgsql
 STABLE SECURITY DEFINER
 SET search_path TO 'public'
AS $function$
DECLARE
  v_day numeric;
  v_week numeric;
  v_month numeric;
BEGIN
  IF auth.uid() <> p_user_id AND NOT public.has_role(auth.uid(), 'admin'::app_role) THEN
    RAISE EXCEPTION 'Not authorized';
  END IF;

  SELECT COALESCE(SUM(amount), 0) INTO v_day
  FROM orders WHERE user_id = p_user_id AND status = 'paid' AND created_at >= now() - interval '1 day';
  SELECT COALESCE(SUM(amount), 0) INTO v_week
  FROM orders WHERE user_id = p_user_id AND status = 'paid' AND created_at >= now() - interval '7 days';
  SELECT COALESCE(SUM(amount), 0) INTO v_month
  FROM orders WHERE user_id = p_user_id AND status = 'paid' AND created_at >= now() - interval '30 days';

  RETURN jsonb_build_object(
    'day', v_day,
    'week', v_week,
    'month', v_month
  );
END;
$function$
```

### get_user_won_prize_tickets

```sql
CREATE OR REPLACE FUNCTION public.get_user_won_prize_tickets()
 RETURNS TABLE(prize_id uuid, prize_name text, prize_value numeric, prize_type text, winning_ticket_number integer)
 LANGUAGE plpgsql
 STABLE SECURITY DEFINER
 SET search_path TO 'public'
AS $function$
BEGIN
  IF auth.uid() IS NULL THEN
    RETURN;
  END IF;
  RETURN QUERY
    SELECT DISTINCT p.id, p.prize_name, p.prize_value, p.prize_type, p.winning_ticket_number
    FROM public.instant_win_prizes p
    JOIN public.instant_win_results r ON r.prize_id = p.id
    WHERE r.user_id = auth.uid() AND r.is_winner = true;
END;
$function$
```

### guard_instant_win_results_update

```sql
CREATE OR REPLACE FUNCTION public.guard_instant_win_results_update()
 RETURNS trigger
 LANGUAGE plpgsql
 SECURITY DEFINER
 SET search_path TO 'public'
AS $function$
BEGIN
  IF public.has_role(auth.uid(), 'admin'::app_role) THEN
    RETURN NEW;
  END IF;

  IF NEW.is_winner IS DISTINCT FROM OLD.is_winner
     OR NEW.prize_id IS DISTINCT FROM OLD.prize_id
     OR NEW.fulfillment_status IS DISTINCT FROM OLD.fulfillment_status
     OR NEW.user_id IS DISTINCT FROM OLD.user_id
     OR NEW.competition_id IS DISTINCT FROM OLD.competition_id
     OR NEW.entry_id IS DISTINCT FROM OLD.entry_id THEN
    RAISE EXCEPTION 'Not permitted to modify protected fields on instant_win_results';
  END IF;

  RETURN NEW;
END;
$function$
```

### guard_user_limits_update

```sql
CREATE OR REPLACE FUNCTION public.guard_user_limits_update()
 RETURNS trigger
 LANGUAGE plpgsql
 SECURITY DEFINER
 SET search_path TO 'public'
AS $function$
BEGIN
  IF public.has_role(auth.uid(), 'admin'::app_role) THEN
    RETURN NEW;
  END IF;

  -- Active cool-off / self-exclusion cannot be shortened or cleared
  IF OLD.self_excluded_until IS NOT NULL AND OLD.self_excluded_until > now() THEN
    IF NEW.self_excluded_until IS NULL OR NEW.self_excluded_until < OLD.self_excluded_until THEN
      RAISE EXCEPTION 'Cannot shorten an active self-exclusion. Contact support.';
    END IF;
  END IF;

  IF OLD.cool_off_until IS NOT NULL AND OLD.cool_off_until > now() THEN
    IF NEW.cool_off_until IS NULL OR NEW.cool_off_until < OLD.cool_off_until THEN
      RAISE EXCEPTION 'Cannot shorten an active cool-off period. Contact support.';
    END IF;
  END IF;

  RETURN NEW;
END;
$function$
```

### guard_winners_update

```sql
CREATE OR REPLACE FUNCTION public.guard_winners_update()
 RETURNS trigger
 LANGUAGE plpgsql
 SECURITY DEFINER
 SET search_path TO 'public'
AS $function$
BEGIN
  IF public.has_role(auth.uid(), 'admin'::app_role) THEN
    RETURN NEW;
  END IF;

  IF NEW.verification_status IS DISTINCT FROM OLD.verification_status
     OR NEW.verified_at IS DISTINCT FROM OLD.verified_at
     OR NEW.verified_by_admin_id IS DISTINCT FROM OLD.verified_by_admin_id
     OR NEW.verification_notes IS DISTINCT FROM OLD.verification_notes
     OR NEW.prize_dispatched IS DISTINCT FROM OLD.prize_dispatched
     OR NEW.prize_dispatched_at IS DISTINCT FROM OLD.prize_dispatched_at
     OR NEW.winner_name IS DISTINCT FROM OLD.winner_name
     OR NEW.prize_description IS DISTINCT FROM OLD.prize_description
     OR NEW.announced IS DISTINCT FROM OLD.announced
     OR NEW.user_id IS DISTINCT FROM OLD.user_id
     OR NEW.competition_id IS DISTINCT FROM OLD.competition_id
     OR NEW.winner_image_url IS DISTINCT FROM OLD.winner_image_url THEN
    -- Allow the winning user to submit/re-submit ID for verification.
    IF NEW.verification_status = 'id_uploaded'
       AND OLD.verification_status IN ('pending', 'rejected')
       AND NEW.id_document_url IS DISTINCT FROM OLD.id_document_url
       AND NEW.verified_at IS NOT DISTINCT FROM OLD.verified_at
       AND NEW.verified_by_admin_id IS NOT DISTINCT FROM OLD.verified_by_admin_id
       AND NEW.verification_notes IS NOT DISTINCT FROM OLD.verification_notes
       AND NEW.prize_dispatched IS NOT DISTINCT FROM OLD.prize_dispatched
       AND NEW.prize_dispatched_at IS NOT DISTINCT FROM OLD.prize_dispatched_at
       AND NEW.winner_name IS NOT DISTINCT FROM OLD.winner_name
       AND NEW.prize_description IS NOT DISTINCT FROM OLD.prize_description
       AND NEW.announced IS NOT DISTINCT FROM OLD.announced
       AND NEW.user_id IS NOT DISTINCT FROM OLD.user_id
       AND NEW.competition_id IS NOT DISTINCT FROM OLD.competition_id
       AND NEW.winner_image_url IS NOT DISTINCT FROM OLD.winner_image_url THEN
      RETURN NEW;
    END IF;
    RAISE EXCEPTION 'Not permitted to modify protected fields on winners';
  END IF;

  RETURN NEW;
END;
$function$
```

### handle_entry_payment_completed

```sql
CREATE OR REPLACE FUNCTION public.handle_entry_payment_completed()
 RETURNS trigger
 LANGUAGE plpgsql
 SECURITY DEFINER
 SET search_path TO 'public'
AS $function$
BEGIN
  -- Only increment when payment_status changes to 'completed'
  IF NEW.payment_status = 'completed' AND (OLD.payment_status IS NULL OR OLD.payment_status != 'completed') THEN
    UPDATE competitions
    SET entries_sold = entries_sold + NEW.ticket_count
    WHERE id = NEW.competition_id;
  END IF;
  
  RETURN NEW;
END;
$function$
```

### handle_new_user

```sql
CREATE OR REPLACE FUNCTION public.handle_new_user()
 RETURNS trigger
 LANGUAGE plpgsql
 SECURITY DEFINER
 SET search_path TO 'public'
AS $function$
BEGIN
  INSERT INTO public.profiles (user_id, email, full_name)
  VALUES (
    NEW.id,
    NEW.email,
    COALESCE(NEW.raw_user_meta_data ->> 'full_name', '')
  );
  
  INSERT INTO public.user_roles (user_id, role)
  VALUES (NEW.id, 'user');
  
  RETURN NEW;
END;
$function$
```

### has_role

```sql
CREATE OR REPLACE FUNCTION public.has_role(_user_id uuid, _role app_role)
 RETURNS boolean
 LANGUAGE sql
 STABLE SECURITY DEFINER
 SET search_path TO 'public'
AS $function$
  SELECT EXISTS (
    SELECT 1
    FROM public.user_roles
    WHERE user_id = _user_id
      AND role = _role
  )
$function$
```

### increment_entries_sold

```sql
CREATE OR REPLACE FUNCTION public.increment_entries_sold(p_competition_id uuid, p_count integer DEFAULT 1)
 RETURNS void
 LANGUAGE plpgsql
 SECURITY DEFINER
 SET search_path TO 'public'
AS $function$
BEGIN
  UPDATE competitions
  SET entries_sold = entries_sold + p_count
  WHERE id = p_competition_id;
END;
$function$
```

### player_board_auto_draw

```sql
CREATE OR REPLACE FUNCTION public.player_board_auto_draw(p_competition_id uuid)
 RETURNS jsonb
 LANGUAGE plpgsql
 SECURITY DEFINER
 SET search_path TO 'public'
AS $function$
DECLARE
  v_competition record;
  v_total_spaces integer;
  v_sold_spaces integer;
  v_winning_space record;
  v_winner_profile record;
BEGIN
  SELECT * INTO v_competition FROM competitions 
  WHERE id = p_competition_id AND competition_type = 'player_board'
  FOR UPDATE;
  
  IF NOT FOUND THEN
    RETURN jsonb_build_object('error', 'Not a player board competition');
  END IF;
  
  IF v_competition.status IN ('ended', 'completed') THEN
    RETURN jsonb_build_object('error', 'Already drawn', 'already_drawn', true);
  END IF;
  
  SELECT COUNT(*) INTO v_total_spaces FROM competition_spaces WHERE competition_id = p_competition_id;
  SELECT COUNT(*) INTO v_sold_spaces FROM competition_spaces WHERE competition_id = p_competition_id AND status = 'sold';
  
  IF v_sold_spaces < v_total_spaces THEN
    RETURN jsonb_build_object('error', 'Not all spaces sold', 'sold', v_sold_spaces, 'total', v_total_spaces);
  END IF;
  
  SELECT * INTO v_winning_space FROM competition_spaces
  WHERE competition_id = p_competition_id AND status = 'sold'
  ORDER BY random() LIMIT 1 FOR UPDATE;
  
  IF NOT FOUND THEN
    RETURN jsonb_build_object('error', 'No sold spaces found');
  END IF;
  
  UPDATE competition_spaces SET status = 'winner', updated_at = now() WHERE id = v_winning_space.id;
  UPDATE competitions SET status = 'ended', updated_at = now() WHERE id = p_competition_id;
  
  SELECT full_name, email INTO v_winner_profile FROM profiles WHERE user_id = v_winning_space.user_id;
  
  INSERT INTO winners (competition_id, user_id, winner_name, prize_description, verification_status)
  VALUES (p_competition_id, v_winning_space.user_id, COALESCE(v_winner_profile.full_name, 'Unknown'), v_competition.description, 'pending');
  
  INSERT INTO draw_audits (
    competition_id, performed_by_admin_id, total_paid_entries, total_postal_entries,
    total_tickets, winning_ticket_number, winner_entry_id, winner_name, winner_email,
    audit_hash, random_seed, winner_type
  ) VALUES (
    p_competition_id, '00000000-0000-0000-0000-000000000000'::uuid,
    v_sold_spaces, 0, v_sold_spaces, v_winning_space.space_number,
    COALESCE(v_winning_space.entry_id, v_winning_space.id),
    COALESCE(v_winner_profile.full_name, 'Unknown'),
    COALESCE(v_winner_profile.email, ''),
    encode(sha256(concat(p_competition_id::text, v_winning_space.id::text, now()::text)::bytea), 'hex'),
    gen_random_uuid()::text, 'paid'
  );
  
  RETURN jsonb_build_object(
    'success', true,
    'winner_space_number', v_winning_space.space_number,
    'winner_player_name', v_winning_space.player_name,
    'winner_user_id', v_winning_space.user_id,
    'winner_name', COALESCE(v_winner_profile.full_name, 'Unknown'),
    'winner_email', COALESCE(v_winner_profile.email, ''),
    'total_spaces', v_total_spaces
  );
END;
$function$
```

### process_instant_win

```sql
CREATE OR REPLACE FUNCTION public.process_instant_win(p_entry_id uuid, p_competition_id uuid, p_user_id uuid)
 RETURNS jsonb
 LANGUAGE plpgsql
 SECURITY DEFINER
 SET search_path TO 'public'
AS $function$
DECLARE
  v_competition record;
  v_entry record;
  v_prize record;
  v_ticket_start integer;
  v_ticket_end integer;
  v_random boolean;
  v_caller uuid;
  v_wins jsonb := '[]'::jsonb;
  v_first jsonb;
BEGIN
  v_caller := auth.uid();
  IF v_caller IS NOT NULL
     AND v_caller IS DISTINCT FROM p_user_id
     AND NOT public.has_role(v_caller, 'admin'::app_role) THEN
    RETURN jsonb_build_object('error', 'Not authorized');
  END IF;

  SELECT * INTO v_competition FROM competitions WHERE id = p_competition_id AND is_instant_win = true;
  IF NOT FOUND THEN
    RETURN jsonb_build_object('error', 'Not an instant win competition');
  END IF;

  v_random := COALESCE(v_competition.random_ticket_allocation, false);

  SELECT * INTO v_entry FROM entries WHERE id = p_entry_id AND competition_id = p_competition_id FOR UPDATE;
  IF NOT FOUND THEN
    RETURN jsonb_build_object('error', 'Entry not found');
  END IF;

  IF v_entry.user_id IS DISTINCT FROM p_user_id THEN
    RETURN jsonb_build_object('error', 'Not authorized');
  END IF;

  IF EXISTS (SELECT 1 FROM instant_win_results WHERE entry_id = p_entry_id) THEN
    RETURN jsonb_build_object('is_winner', false, 'already_processed', true);
  END IF;

  IF NOT v_random THEN
    v_ticket_start := v_entry.ticket_start_number;
    v_ticket_end := v_entry.ticket_start_number + v_entry.ticket_count - 1;
  END IF;

  FOR v_prize IN
    SELECT * FROM instant_win_prizes p
    WHERE p.competition_id = p_competition_id
      AND p.winning_ticket_number IS NOT NULL
      AND p.claimed_quantity < p.total_quantity
      AND (
        (v_random AND EXISTS (
          SELECT 1 FROM entry_tickets t
          WHERE t.entry_id = p_entry_id AND t.ticket_number = p.winning_ticket_number
        ))
        OR
        (NOT v_random
          AND v_ticket_start IS NOT NULL
          AND p.winning_ticket_number >= v_ticket_start
          AND p.winning_ticket_number <= v_ticket_end)
      )
    ORDER BY p.prize_tier ASC
    FOR UPDATE
  LOOP
    UPDATE instant_win_prizes SET claimed_quantity = claimed_quantity + 1 WHERE id = v_prize.id;

    INSERT INTO instant_win_results (competition_id, entry_id, user_id, prize_id, is_winner)
    VALUES (p_competition_id, p_entry_id, p_user_id, v_prize.id, true);

    IF v_prize.prize_type = 'site_credit' THEN
      INSERT INTO site_credits (user_id, amount, type, reason, expires_at)
      VALUES (p_user_id, v_prize.prize_value, 'grant', 'Instant win prize: ' || v_prize.prize_name, now() + interval '30 days');
    END IF;

    v_wins := v_wins || jsonb_build_object(
      'prize_name', v_prize.prize_name,
      'prize_value', v_prize.prize_value,
      'prize_id', v_prize.id,
      'prize_type', v_prize.prize_type,
      'winning_ticket_number', v_prize.winning_ticket_number
    );
  END LOOP;

  IF jsonb_array_length(v_wins) = 0 THEN
    RETURN jsonb_build_object('is_winner', false);
  END IF;

  v_first := v_wins->0;
  RETURN jsonb_build_object(
    'is_winner', true,
    'prize_name', v_first->>'prize_name',
    'prize_value', (v_first->>'prize_value')::numeric,
    'prize_id', v_first->>'prize_id',
    'prize_type', v_first->>'prize_type',
    'wins', v_wins,
    'win_count', jsonb_array_length(v_wins)
  );
END;
$function$
```

### set_competition_slug

```sql
CREATE OR REPLACE FUNCTION public.set_competition_slug()
 RETURNS trigger
 LANGUAGE plpgsql
 SET search_path TO 'public'
AS $function$
BEGIN
  IF NEW.slug IS NULL OR NEW.slug = '' THEN
    NEW.slug := public.generate_unique_competition_slug(NEW.title, NEW.id);
  END IF;
  RETURN NEW;
END;
$function$
```

### slugify

```sql
CREATE OR REPLACE FUNCTION public.slugify(p_text text)
 RETURNS text
 LANGUAGE sql
 IMMUTABLE
 SET search_path TO 'public'
AS $function$
  SELECT trim(both '-' from regexp_replace(
    regexp_replace(lower(coalesce(p_text, '')), '[^a-z0-9]+', '-', 'g'),
    '-+', '-', 'g'
  ));
$function$
```

### update_updated_at_column

```sql
CREATE OR REPLACE FUNCTION public.update_updated_at_column()
 RETURNS trigger
 LANGUAGE plpgsql
 SET search_path TO 'public'
AS $function$
BEGIN
  NEW.updated_at = now();
  RETURN NEW;
END;
$function$
```

### upload_winner_id_document

```sql
CREATE OR REPLACE FUNCTION public.upload_winner_id_document(p_winner_id uuid, p_file_path text)
 RETURNS void
 LANGUAGE plpgsql
 SECURITY DEFINER
 SET search_path TO 'public'
AS $function$
BEGIN
  IF auth.uid() IS NULL THEN
    RAISE EXCEPTION 'Not signed in';
  END IF;

  IF p_file_path IS NULL OR length(trim(p_file_path)) = 0 THEN
    RAISE EXCEPTION 'Missing file path';
  END IF;

  UPDATE public.winners
  SET
    id_document_url = p_file_path,
    id_document_uploaded_at = now(),
    verification_status = 'id_uploaded'
  WHERE id = p_winner_id
    AND user_id = auth.uid()
    AND verification_status IN ('pending', 'rejected');

  IF NOT FOUND THEN
    RAISE EXCEPTION 'Winner record not found or not eligible for upload';
  END IF;
END;
$function$
```

### validate_promo_code

```sql
CREATE OR REPLACE FUNCTION public.validate_promo_code(p_code text)
 RETURNS TABLE(code text, discount_type text, discount_value numeric, excluded_competition_ids text[])
 LANGUAGE plpgsql
 STABLE SECURITY DEFINER
 SET search_path TO 'public'
AS $function$
DECLARE
  r record;
BEGIN
  SELECT * INTO r
  FROM public.promo_codes pc
  WHERE UPPER(pc.code) = UPPER(TRIM(p_code))
    AND pc.is_active = true
  LIMIT 1;

  IF NOT FOUND THEN
    RETURN;
  END IF;

  IF r.valid_until IS NOT NULL AND r.valid_until < now() THEN
    RETURN;
  END IF;

  IF r.max_uses IS NOT NULL AND r.times_used >= r.max_uses THEN
    RETURN;
  END IF;

  RETURN QUERY SELECT
    r.code,
    r.discount_type,
    r.discount_value,
    COALESCE(r.excluded_competition_ids, ARRAY[]::text[]);
END;
$function$
```

### validate_quiz_answer

```sql
CREATE OR REPLACE FUNCTION public.validate_quiz_answer(p_competition_id uuid, p_user_answer text)
 RETURNS boolean
 LANGUAGE plpgsql
 SECURITY DEFINER
 SET search_path TO 'public'
AS $function$
BEGIN
  RETURN EXISTS (
    SELECT 1 FROM competitions 
    WHERE id = p_competition_id 
    AND LOWER(TRIM(quiz_answer)) = LOWER(TRIM(p_user_answer))
  );
END;
$function$
```

### verify_ticket_in_draw

```sql
CREATE OR REPLACE FUNCTION public.verify_ticket_in_draw(p_audit_id uuid, p_ticket_number integer)
 RETURNS jsonb
 LANGUAGE plpgsql
 STABLE SECURITY DEFINER
 SET search_path TO 'public'
AS $function$
DECLARE
  v_audit record;
  v_paid_match boolean := false;
  v_postal_total integer;
  v_random boolean;
  v_ticket record;
BEGIN
  SELECT * INTO v_audit
  FROM draw_audits
  WHERE id = p_audit_id AND is_published = true;

  IF NOT FOUND THEN
    RETURN jsonb_build_object('found', false, 'error', 'Audit not found or not published');
  END IF;

  IF p_ticket_number < 1 OR p_ticket_number > v_audit.total_tickets THEN
    RETURN jsonb_build_object(
      'found', false,
      'ticket_number', p_ticket_number,
      'total_tickets', v_audit.total_tickets,
      'message', 'Ticket number was outside the draw pool'
    );
  END IF;

  SELECT COALESCE(random_ticket_allocation, false) INTO v_random
  FROM competitions WHERE id = v_audit.competition_id;

  IF v_random THEN
    SELECT * INTO v_ticket
    FROM entry_tickets
    WHERE competition_id = v_audit.competition_id AND ticket_number = p_ticket_number;

    IF FOUND THEN
      RETURN jsonb_build_object(
        'found', true,
        'ticket_number', p_ticket_number,
        'entry_type', CASE WHEN v_ticket.postal_entry_id IS NOT NULL THEN 'postal' ELSE 'paid' END,
        'is_winning_ticket', (p_ticket_number = v_audit.winning_ticket_number),
        'total_tickets', v_audit.total_tickets
      );
    END IF;

    RETURN jsonb_build_object(
      'found', false,
      'ticket_number', p_ticket_number,
      'total_tickets', v_audit.total_tickets,
      'message', 'Ticket number was not allocated in this draw'
    );
  END IF;

  SELECT COALESCE(SUM(ticket_count), 0) INTO v_postal_total
  FROM postal_entries
  WHERE competition_id = v_audit.competition_id;

  IF p_ticket_number <= v_postal_total THEN
    RETURN jsonb_build_object(
      'found', true,
      'ticket_number', p_ticket_number,
      'entry_type', 'postal',
      'is_winning_ticket', (p_ticket_number = v_audit.winning_ticket_number),
      'total_tickets', v_audit.total_tickets
    );
  END IF;

  SELECT EXISTS (
    SELECT 1 FROM entries
    WHERE competition_id = v_audit.competition_id
      AND payment_status = 'completed'
      AND ticket_start_number IS NOT NULL
      AND p_ticket_number >= ticket_start_number
      AND p_ticket_number <= (ticket_start_number + ticket_count - 1)
  ) INTO v_paid_match;

  IF v_paid_match THEN
    RETURN jsonb_build_object(
      'found', true,
      'ticket_number', p_ticket_number,
      'entry_type', 'paid',
      'is_winning_ticket', (p_ticket_number = v_audit.winning_ticket_number),
      'total_tickets', v_audit.total_tickets
    );
  END IF;

  RETURN jsonb_build_object(
    'found', false,
    'ticket_number', p_ticket_number,
    'total_tickets', v_audit.total_tickets,
    'message', 'Ticket number was not allocated in this draw'
  );
END;
$function$
```

## Triggers on auth schema

```sql
CREATE TRIGGER on_auth_user_created AFTER INSERT ON auth.users FOR EACH ROW EXECUTE FUNCTION handle_new_user();
```

## Storage buckets

| Bucket | Public | Size limit | Allowed MIME | Objects |
|---|---|---|---|---:|
| competition-images | t | none | any | 509 |
| email-assets | t | none | any | 3 |
| winner-id-documents | f | none | any | 216 |

URL generation: `competition-images` and `email-assets` are public — the app uses `supabase.storage.from(bucket).getPublicUrl(path)` (see `src/components/admin/ImageUpload.tsx`). `winner-id-documents` is private — the DB stores the object path in `winners.id_document_url`; admins view via `createSignedUrl` (see `src/pages/admin/AdminWinnerVerification.tsx`). Many competition images were also migrated to the Lovable CDN (`/__l5e/assets-v1/...`).

### storage.objects policies (verbatim)

```sql
CREATE POLICY "Admins can delete competition images" ON storage.objects AS PERMISSIVE FOR DELETE TO public
  USING (((bucket_id = 'competition-images'::text) AND has_role(auth.uid(), 'admin'::app_role)));
CREATE POLICY "Admins can delete winner ID documents" ON storage.objects AS PERMISSIVE FOR DELETE TO public
  USING (((bucket_id = 'winner-id-documents'::text) AND (EXISTS ( SELECT 1
   FROM user_roles
  WHERE ((user_roles.user_id = auth.uid()) AND (user_roles.role = 'admin'::app_role))))));
CREATE POLICY "Admins can read winner ID documents" ON storage.objects AS PERMISSIVE FOR SELECT TO authenticated
  USING (((bucket_id = 'winner-id-documents'::text) AND has_role(auth.uid(), 'admin'::app_role)));
CREATE POLICY "Admins can update competition images" ON storage.objects AS PERMISSIVE FOR UPDATE TO public
  USING (((bucket_id = 'competition-images'::text) AND has_role(auth.uid(), 'admin'::app_role)));
CREATE POLICY "Admins can update winner ID documents" ON storage.objects AS PERMISSIVE FOR UPDATE TO authenticated
  USING (((bucket_id = 'winner-id-documents'::text) AND has_role(auth.uid(), 'admin'::app_role)))
  WITH CHECK (((bucket_id = 'winner-id-documents'::text) AND has_role(auth.uid(), 'admin'::app_role)));
CREATE POLICY "Admins can upload competition images" ON storage.objects AS PERMISSIVE FOR INSERT TO public
  WITH CHECK (((bucket_id = 'competition-images'::text) AND has_role(auth.uid(), 'admin'::app_role)));
CREATE POLICY "Admins can upload winner ID documents" ON storage.objects AS PERMISSIVE FOR INSERT TO authenticated
  WITH CHECK (((bucket_id = 'winner-id-documents'::text) AND has_role(auth.uid(), 'admin'::app_role)));
CREATE POLICY "Admins can view all winner ID documents" ON storage.objects AS PERMISSIVE FOR SELECT TO public
  USING (((bucket_id = 'winner-id-documents'::text) AND (EXISTS ( SELECT 1
   FROM user_roles
  WHERE ((user_roles.user_id = auth.uid()) AND (user_roles.role = 'admin'::app_role))))));
CREATE POLICY "Anyone can view competition images" ON storage.objects AS PERMISSIVE FOR SELECT TO public
  USING ((bucket_id = 'competition-images'::text));
CREATE POLICY "Winners can upload their own ID" ON storage.objects AS PERMISSIVE FOR INSERT TO public
  WITH CHECK (((bucket_id = 'winner-id-documents'::text) AND ((auth.uid())::text = (storage.foldername(name))[1])));
CREATE POLICY "Winners can upload their own ID documents" ON storage.objects AS PERMISSIVE FOR INSERT TO authenticated
  WITH CHECK (((bucket_id = 'winner-id-documents'::text) AND ((storage.foldername(name))[1] = (auth.uid())::text)));
CREATE POLICY "Winners can view their own ID" ON storage.objects AS PERMISSIVE FOR SELECT TO public
  USING (((bucket_id = 'winner-id-documents'::text) AND ((auth.uid())::text = (storage.foldername(name))[1])));
```

## Cron jobs (pg_cron, verbatim; secrets [REDACTED])

| jobid | Name | Schedule | Active | Purpose |
|---|---|---|---|---|
| 4 | send-draw-reminders-hourly | `0 * * * *` | yes | POST to `send-draw-reminders` edge function |
| 6 | expire-stuck-orders-hourly | `15 * * * *` | yes | POST to `expire-stuck-orders` with `x-cron-secret` |
| 9 | auto-draw-scheduled-every-min (stale name) | `*/5 * * * *` | yes | POST to `auto-draw-scheduled` |
| 12 | nightly-log-cleanup | `15 3 * * *` | yes | Delete webhook_logs >30d, email_logs >90d |
| 13 | publish-scheduled-competitions-5min | `*/5 * * * *` | yes | Flip `scheduled` → `active` when `scheduled_start_at <= now()` |

```sql
-- jobid=4 send-draw-reminders-hourly  schedule='0 * * * *' active=true
SELECT net.http_post(url := 'https://<project>.supabase.co/functions/v1/send-draw-reminders',
  headers := jsonb_build_object('Content-Type','application/json','Authorization','Bearer [REDACTED]'),
  body := '{}'::jsonb) AS request_id;
-- jobid=6 expire-stuck-orders-hourly  schedule='15 * * * *' active=true
SELECT net.http_post(url:='https://<project>.supabase.co/functions/v1/expire-stuck-orders',
  headers:=jsonb_build_object('Content-Type','application/json','x-cron-secret','[REDACTED]'),
  body:='{}'::jsonb);
-- jobid=9 auto-draw-scheduled-every-min (name is stale; runs every 5 min)  schedule='*/5 * * * *' active=true
select net.http_post(url:='https://<project>.supabase.co/functions/v1/auto-draw-scheduled',
  headers:='{"Content-Type": "application/json", "Authorization": "Bearer [REDACTED]"}'::jsonb,
  body:=concat('{"time": "', now(), '"}')::jsonb);
-- jobid=12 nightly-log-cleanup  schedule='15 3 * * *' active=true
DELETE FROM public.webhook_logs WHERE created_at < now() - interval '30 days';
DELETE FROM public.email_logs   WHERE sent_at   < now() - interval '90 days';
-- jobid=13 publish-scheduled-competitions-5min  schedule='*/5 * * * *' active=true
update public.competitions set status='active', updated_at=now() where status='scheduled' and scheduled_start_at <= now();
```

Note: the `publish-scheduled-competitions` edge function still exists but is no longer called by cron (replaced by job 13's direct SQL). The auto-draw job name says every-min but the schedule is every 5 minutes, so auto-draws can fire up to ~5 minutes late.
