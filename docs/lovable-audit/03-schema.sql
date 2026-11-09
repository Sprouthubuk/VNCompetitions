-- VaultNine complete schema export (public schema, schema-only) generated 2026-11-08T23:56:15Z
-- Includes tables, types, functions, triggers, views, indexes, RLS policies.
-- Storage policies, cron jobs and auth trigger appended at end (verbatim from catalogs).
--
-- PostgreSQL database dump
--


-- Dumped from database version 17.6
-- Dumped by pg_dump version 17.11

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: public; Type: SCHEMA; Schema: -; Owner: -
--

CREATE SCHEMA public;


--
-- Name: SCHEMA public; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON SCHEMA public IS 'standard public schema';


--
-- Name: app_role; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE public.app_role AS ENUM (
    'admin',
    'user'
);


--
-- Name: admin_competition_report(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.admin_competition_report() RETURNS TABLE(competition_id text, cash_revenue numeric, entry_count bigint, credit_used numeric, prize_cost_credit numeric, prize_cost_physical numeric)
    LANGUAGE plpgsql SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
DECLARE
  v_cached jsonb;
BEGIN
  IF NOT public.has_role(auth.uid(), 'admin'::app_role) THEN
    RAISE EXCEPTION 'Not authorized';
  END IF;

  SELECT value INTO v_cached
  FROM stats_cache
  WHERE key = '[REDACTED]' AND refreshed_at > now() - interval '5 minutes';

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
$$;


--
-- Name: admin_credit_health(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.admin_credit_health() RETURNS jsonb
    LANGUAGE plpgsql STABLE SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
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
$$;


SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: competitions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.competitions (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    title text NOT NULL,
    description text NOT NULL,
    image_url text,
    ticket_price numeric(10,2) DEFAULT 0.99 NOT NULL,
    max_entries integer DEFAULT 999 NOT NULL,
    entries_sold integer DEFAULT 0 NOT NULL,
    draw_date timestamp with time zone NOT NULL,
    category text DEFAULT 'darts'::text NOT NULL,
    status text DEFAULT 'active'::text NOT NULL,
    is_free boolean DEFAULT false NOT NULL,
    quiz_question text,
    quiz_options text[],
    quiz_answer text,
    includes text[],
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    updated_at timestamp with time zone DEFAULT now() NOT NULL,
    featured boolean DEFAULT false NOT NULL,
    sale_price numeric,
    buy_quantity integer,
    free_quantity integer,
    is_instant_win boolean DEFAULT false NOT NULL,
    competition_type text DEFAULT 'standard'::text NOT NULL,
    max_entries_per_user integer,
    exclude_from_discounts boolean DEFAULT false NOT NULL,
    cost_price numeric DEFAULT 0,
    slug text DEFAULT ''::text NOT NULL,
    scheduled_start_at timestamp with time zone,
    bundle_tiers jsonb DEFAULT '[]'::jsonb NOT NULL,
    cash_alternative_value numeric(10,2) DEFAULT 0 NOT NULL,
    cash_alternative_enabled boolean DEFAULT true NOT NULL,
    random_ticket_allocation boolean DEFAULT true NOT NULL,
    multiplier_min_tickets integer,
    multiplier_value integer,
    draws_on_sellout boolean DEFAULT false NOT NULL,
    prize_purchased boolean DEFAULT false NOT NULL,
    prize_purchased_at timestamp with time zone,
    prize_purchased_by uuid,
    instant_win_game text DEFAULT 'classic'::text NOT NULL,
    CONSTRAINT competitions_instant_win_game_check CHECK ((instant_win_game = ANY (ARRAY['classic'::text, 'dartboard'::text, 'wheel'::text]))),
    CONSTRAINT competitions_status_check CHECK ((status = ANY (ARRAY['active'::text, 'ended'::text, 'coming-soon'::text, 'sold-out'::text, 'scheduled'::text])))
);


--
-- Name: admin_get_competition(uuid); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.admin_get_competition(p_id uuid) RETURNS SETOF public.competitions
    LANGUAGE plpgsql STABLE SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
BEGIN
  IF NOT public.has_role(auth.uid(), 'admin'::app_role) THEN
    RAISE EXCEPTION 'Not authorized';
  END IF;
  RETURN QUERY SELECT * FROM public.competitions WHERE id = p_id;
END;
$$;


--
-- Name: competition_spaces; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.competition_spaces (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    competition_id uuid NOT NULL,
    space_number integer NOT NULL,
    player_name text NOT NULL,
    status text DEFAULT 'available'::text NOT NULL,
    user_id uuid,
    order_id uuid,
    entry_id uuid,
    reserved_at timestamp with time zone,
    reserved_until timestamp with time zone,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    updated_at timestamp with time zone DEFAULT now() NOT NULL
);


--
-- Name: admin_get_competition_spaces(uuid); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.admin_get_competition_spaces(p_competition_id uuid) RETURNS SETOF public.competition_spaces
    LANGUAGE plpgsql STABLE SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
BEGIN
  IF NOT public.has_role(auth.uid(), 'admin'::app_role) THEN
    RAISE EXCEPTION 'Not authorized';
  END IF;
  RETURN QUERY
    SELECT * FROM public.competition_spaces
    WHERE competition_id = p_competition_id
    ORDER BY space_number;
END;
$$;


--
-- Name: instant_win_prizes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.instant_win_prizes (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    competition_id uuid NOT NULL,
    prize_name text NOT NULL,
    prize_value numeric DEFAULT 0 NOT NULL,
    total_quantity integer DEFAULT 1 NOT NULL,
    claimed_quantity integer DEFAULT 0 NOT NULL,
    prize_tier integer DEFAULT 1 NOT NULL,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    prize_type text DEFAULT 'physical'::text NOT NULL,
    winning_ticket_number integer
);


--
-- Name: admin_get_instant_win_prizes(uuid); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.admin_get_instant_win_prizes(p_competition_id uuid) RETURNS SETOF public.instant_win_prizes
    LANGUAGE plpgsql STABLE SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
BEGIN
  IF NOT public.has_role(auth.uid(), 'admin'::app_role) THEN
    RAISE EXCEPTION 'Not authorized';
  END IF;
  RETURN QUERY
    SELECT * FROM public.instant_win_prizes
    WHERE competition_id = p_competition_id
    ORDER BY prize_tier;
END;
$$;


--
-- Name: admin_list_competitions_full(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.admin_list_competitions_full() RETURNS SETOF public.competitions
    LANGUAGE plpgsql STABLE SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
BEGIN
  IF NOT public.has_role(auth.uid(), 'admin'::app_role) THEN
    RAISE EXCEPTION 'Not authorized';
  END IF;
  RETURN QUERY SELECT * FROM public.competitions;
END;
$$;


--
-- Name: winners; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.winners (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    user_id uuid,
    competition_id uuid NOT NULL,
    winner_name text NOT NULL,
    prize_description text NOT NULL,
    won_at timestamp with time zone DEFAULT now() NOT NULL,
    announced boolean DEFAULT false NOT NULL,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    verification_status text DEFAULT 'pending'::text NOT NULL,
    id_document_url text,
    id_document_uploaded_at timestamp with time zone,
    verified_at timestamp with time zone,
    verified_by_admin_id uuid,
    verification_notes text,
    prize_dispatched boolean DEFAULT false NOT NULL,
    prize_dispatched_at timestamp with time zone,
    winner_image_url text,
    CONSTRAINT winners_verification_status_check CHECK ((verification_status = ANY (ARRAY['pending'::text, 'id_uploaded'::text, 'approved'::text, 'rejected'::text])))
);


--
-- Name: admin_list_user_winners(uuid); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.admin_list_user_winners(p_user_id uuid) RETURNS SETOF public.winners
    LANGUAGE plpgsql STABLE SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
BEGIN
  IF NOT public.has_role(auth.uid(), 'admin'::app_role) THEN
    RAISE EXCEPTION 'Not authorized';
  END IF;
  RETURN QUERY SELECT * FROM public.winners WHERE user_id = p_user_id ORDER BY won_at DESC;
END;
$$;


--
-- Name: admin_list_winners_full(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.admin_list_winners_full() RETURNS SETOF public.winners
    LANGUAGE plpgsql STABLE SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
BEGIN
  IF NOT public.has_role(auth.uid(), 'admin'::app_role) THEN
    RAISE EXCEPTION 'Not authorized';
  END IF;
  RETURN QUERY SELECT * FROM public.winners ORDER BY won_at DESC;
END;
$$;


--
-- Name: allocate_random_tickets(uuid, uuid, uuid, uuid, integer); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.allocate_random_tickets(p_competition_id uuid, p_entry_id uuid, p_postal_entry_id uuid, p_user_id uuid, p_count integer) RETURNS integer
    LANGUAGE plpgsql SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
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
$$;


--
-- Name: allocate_random_tickets_for_entry(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.allocate_random_tickets_for_entry() RETURNS trigger
    LANGUAGE plpgsql SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
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
$$;


--
-- Name: allocate_random_tickets_for_postal(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.allocate_random_tickets_for_postal() RETURNS trigger
    LANGUAGE plpgsql SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
BEGIN
  IF (SELECT COALESCE(random_ticket_allocation, false) FROM competitions WHERE id = NEW.competition_id) THEN
    IF NOT EXISTS (SELECT 1 FROM entry_tickets WHERE postal_entry_id = NEW.id) THEN
      PERFORM public.allocate_random_tickets(NEW.competition_id, NULL, NEW.id, NULL, NEW.ticket_count);
    END IF;
  END IF;
  RETURN NEW;
END;
$$;


--
-- Name: assign_postal_ticket_numbers(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.assign_postal_ticket_numbers() RETURNS trigger
    LANGUAGE plpgsql SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
DECLARE v_paid_end integer; v_postal_end integer; v_legacy integer; BEGIN
 PERFORM 1 FROM public.competitions WHERE id=NEW.competition_id FOR UPDATE;
 IF (SELECT COALESCE(random_ticket_allocation,false) FROM public.competitions WHERE id=NEW.competition_id) THEN RETURN NEW; END IF;
 SELECT COALESCE(MAX(ticket_start_number+ticket_count-1),0) INTO v_paid_end FROM public.entries WHERE competition_id=NEW.competition_id AND payment_status='completed';
 SELECT COALESCE(MAX(ticket_start_number+ticket_count-1),0),COALESCE(SUM(ticket_count) FILTER(WHERE ticket_start_number IS NULL),0) INTO v_postal_end,v_legacy FROM public.postal_entries WHERE competition_id=NEW.competition_id;
 NEW.ticket_start_number:=GREATEST(v_paid_end+v_legacy,v_postal_end)+1;
 RETURN NEW;
END $$;


--
-- Name: assign_ticket_numbers(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.assign_ticket_numbers() RETURNS trigger
    LANGUAGE plpgsql SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
DECLARE v_end integer; v_postal_end integer; BEGIN
 IF NEW.payment_status='completed' AND NEW.ticket_start_number IS NULL THEN
  PERFORM 1 FROM public.competitions WHERE id=NEW.competition_id FOR UPDATE;
  IF (SELECT COALESCE(random_ticket_allocation,false) FROM public.competitions WHERE id=NEW.competition_id) THEN RETURN NEW; END IF;
  SELECT COALESCE(MAX(ticket_start_number+ticket_count-1),0) INTO v_end FROM public.entries WHERE competition_id=NEW.competition_id AND payment_status='completed' AND id<>NEW.id;
  SELECT GREATEST(COALESCE(MAX(ticket_start_number+ticket_count-1),0),COALESCE(SUM(ticket_count),0)) INTO v_postal_end FROM public.postal_entries WHERE competition_id=NEW.competition_id;
  NEW.ticket_start_number:=GREATEST(v_end,v_postal_end)+1;
 END IF;
 RETURN NEW;
END $$;


--
-- Name: check_competition_sold_out(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.check_competition_sold_out() RETURNS trigger
    LANGUAGE plpgsql SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
BEGIN
  -- Check if entries_sold equals or exceeds max_entries
  IF NEW.entries_sold >= NEW.max_entries AND NEW.status = 'active' THEN
    NEW.status := 'sold-out';
  END IF;
  RETURN NEW;
END;
$$;


--
-- Name: check_user_spend_limit(uuid, numeric); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.check_user_spend_limit(p_user_id uuid, p_amount numeric) RETURNS jsonb
    LANGUAGE plpgsql STABLE SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
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
$$;


--
-- Name: competition_person_entry_count(uuid, uuid, text, uuid, uuid); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.competition_person_entry_count(p_comp uuid, p_user uuid, p_email text, p_exclude_entry uuid DEFAULT NULL::uuid, p_exclude_postal uuid DEFAULT NULL::uuid) RETURNS integer
    LANGUAGE sql STABLE SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
 SELECT (COALESCE((SELECT SUM(e.ticket_count) FROM public.entries e WHERE e.competition_id=p_comp AND e.payment_status='completed' AND e.id IS DISTINCT FROM p_exclude_entry AND (e.user_id=p_user OR e.user_id IN (SELECT user_id FROM public.profiles WHERE lower(trim(email))=lower(trim(p_email))))),0)
 + COALESCE((SELECT SUM(p.ticket_count) FROM public.postal_entries p WHERE p.competition_id=p_comp AND p.id IS DISTINCT FROM p_exclude_postal AND lower(trim(p.email))=lower(trim(p_email))),0))::integer
$$;


--
-- Name: competition_person_reserved_count(uuid, uuid, text, uuid); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.competition_person_reserved_count(p_comp uuid, p_user uuid, p_email text, p_exclude_order uuid DEFAULT NULL::uuid) RETURNS integer
    LANGUAGE sql STABLE SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
 SELECT COALESCE(SUM(GREATEST(0,
 CASE WHEN jsonb_typeof(o.metadata->'items')='array' THEN
   COALESCE((SELECT SUM(COALESCE((i->>'totalTickets')::integer,(i->>'ticketCount')::integer,0)) FROM jsonb_array_elements(o.metadata->'items') i WHERE i->>'competitionId'=p_comp::text),0)
 ELSE CASE WHEN o.metadata->>'competitionId'=p_comp::text THEN COALESCE(jsonb_array_length(o.metadata->'spaceIds'),0) ELSE 0 END END
 - COALESCE((SELECT SUM(e.ticket_count) FROM public.entries e WHERE e.competition_id=p_comp AND e.user_id=o.user_id AND e.payment_status='completed' AND (e.id::text=ANY(COALESCE(o.entry_ids,ARRAY[]::text[])) OR (e.payment_intent_id IS NOT NULL AND e.payment_intent_id=o.cashflows_payment_reference))),0)
 )),0)::integer
 FROM public.orders o WHERE o.id IS DISTINCT FROM p_exclude_order AND (o.user_id=p_user OR o.user_id IN (SELECT user_id FROM public.profiles WHERE lower(trim(email))=lower(trim(p_email))))
 AND ((o.status='pending_payment' AND o.created_at>now()-interval '30 minutes') OR (o.status='paid' AND cardinality(COALESCE(o.entry_ids,ARRAY[]::text[]))=0 AND o.created_at>now()-interval '30 minutes'))
$$;


--
-- Name: consume_promo_code(text); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.consume_promo_code(p_code text) RETURNS jsonb
    LANGUAGE plpgsql SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
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
$$;


--
-- Name: deduct_site_credit(uuid, numeric, text, uuid); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.deduct_site_credit(p_user_id uuid, p_amount numeric, p_reason text, p_order_id uuid) RETURNS numeric
    LANGUAGE plpgsql SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
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
$$;


--
-- Name: enforce_one_free_entry_per_user(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.enforce_one_free_entry_per_user() RETURNS trigger
    LANGUAGE plpgsql SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
BEGIN
 -- Deprecated single-entry restriction; aa_shared_entry_limit now enforces 50.
 RETURN NEW;
END $$;


--
-- Name: enforce_order_entry_limit(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.enforce_order_entry_limit() RETURNS trigger
    LANGUAGE plpgsql SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
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
END $$;


--
-- Name: FUNCTION enforce_order_entry_limit(); Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON FUNCTION public.enforce_order_entry_limit() IS 'Validates positive requested entry counts and preserves competition locking; no per-person ticket cap.';


--
-- Name: enforce_shared_entry_limit(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.enforce_shared_entry_limit() RETURNS trigger
    LANGUAGE plpgsql SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
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
END $$;


--
-- Name: FUNCTION enforce_shared_entry_limit(); Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON FUNCTION public.enforce_shared_entry_limit() IS 'Validates positive completed entry counts and one postal entry per envelope; no per-person ticket cap.';


--
-- Name: enter_free_competition(uuid, integer); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.enter_free_competition(p_competition_id uuid, p_ticket_count integer DEFAULT 1) RETURNS uuid
    LANGUAGE plpgsql SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
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
END $$;


--
-- Name: estimate_payment_fee(numeric); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.estimate_payment_fee(p_amount numeric) RETURNS numeric
    LANGUAGE sql IMMUTABLE
    SET search_path TO 'public'
    AS $$
  SELECT CASE WHEN COALESCE(p_amount,0) <= 0 THEN 0
              ELSE ROUND((p_amount * 0.019 + 0.20)::numeric, 2) END
$$;


--
-- Name: expire_space_reservations(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.expire_space_reservations() RETURNS integer
    LANGUAGE plpgsql SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
DECLARE v_count integer;
BEGIN
  UPDATE competition_spaces
  SET status = 'available', user_id = NULL, order_id = NULL, reserved_at = NULL, reserved_until = NULL, updated_at = now()
  WHERE status = 'reserved' AND reserved_until < now();
  GET DIAGNOSTICS v_count = ROW_COUNT;
  RETURN v_count;
END;
$$;


--
-- Name: force_sequential_for_instant_win(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.force_sequential_for_instant_win() RETURNS trigger
    LANGUAGE plpgsql
    SET search_path TO 'public'
    AS $$
BEGIN
  IF NEW.is_instant_win THEN
    NEW.random_ticket_allocation := false;
  END IF;
  RETURN NEW;
END; $$;


--
-- Name: generate_unique_competition_slug(text, uuid); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.generate_unique_competition_slug(p_title text, p_id uuid) RETURNS text
    LANGUAGE plpgsql
    SET search_path TO 'public'
    AS $$
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
$$;


--
-- Name: get_announced_draw_results(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.get_announced_draw_results() RETURNS TABLE(competition_id uuid, winning_ticket_number integer, total_tickets integer)
    LANGUAGE sql STABLE SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
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
$$;


--
-- Name: get_instant_win_prize_status(uuid); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.get_instant_win_prize_status(p_competition_id uuid) RETURNS TABLE(group_key text, prize_name text, prize_value numeric, prize_tier integer, prize_type text, total_quantity integer, claimed_quantity integer, winners jsonb)
    LANGUAGE sql STABLE SECURITY DEFINER
    SET search_path TO 'public'
    AS $_$
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
$_$;


--
-- Name: get_my_competition_entry_count(uuid); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.get_my_competition_entry_count(p_competition_id uuid) RETURNS integer
    LANGUAGE plpgsql STABLE SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
DECLARE v_email text; BEGIN
 IF auth.uid() IS NULL THEN RAISE EXCEPTION 'Please sign in'; END IF;
 SELECT email INTO v_email FROM public.profiles WHERE user_id=auth.uid();
 RETURN public.competition_person_entry_count(p_competition_id,auth.uid(),v_email);
END $$;


--
-- Name: get_my_competition_spaces(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.get_my_competition_spaces() RETURNS SETOF public.competition_spaces
    LANGUAGE plpgsql STABLE SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
BEGIN
  IF auth.uid() IS NULL THEN
    RETURN;
  END IF;
  RETURN QUERY
    SELECT * FROM public.competition_spaces
    WHERE user_id = auth.uid()
    ORDER BY created_at DESC;
END;
$$;


--
-- Name: get_my_winner_records(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.get_my_winner_records() RETURNS TABLE(id uuid, competition_id uuid, winner_name text, prize_description text, verification_status text, id_document_url text, verified_at timestamp with time zone, prize_dispatched boolean, won_at timestamp with time zone, created_at timestamp with time zone)
    LANGUAGE sql STABLE SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
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
$$;


--
-- Name: get_platform_stats(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.get_platform_stats() RETURNS jsonb
    LANGUAGE plpgsql SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
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
$$;


--
-- Name: get_user_credit_balance(uuid); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.get_user_credit_balance(p_user_id uuid) RETURNS numeric
    LANGUAGE plpgsql STABLE SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
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
$$;


--
-- Name: get_user_spend_summary(uuid); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.get_user_spend_summary(p_user_id uuid) RETURNS jsonb
    LANGUAGE plpgsql STABLE SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
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
$$;


--
-- Name: get_user_won_prize_tickets(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.get_user_won_prize_tickets() RETURNS TABLE(prize_id uuid, prize_name text, prize_value numeric, prize_type text, winning_ticket_number integer)
    LANGUAGE plpgsql STABLE SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
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
$$;


--
-- Name: guard_instant_win_results_update(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.guard_instant_win_results_update() RETURNS trigger
    LANGUAGE plpgsql SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
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
$$;


--
-- Name: guard_user_limits_update(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.guard_user_limits_update() RETURNS trigger
    LANGUAGE plpgsql SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
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
$$;


--
-- Name: guard_winners_update(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.guard_winners_update() RETURNS trigger
    LANGUAGE plpgsql SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
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
$$;


--
-- Name: handle_entry_payment_completed(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.handle_entry_payment_completed() RETURNS trigger
    LANGUAGE plpgsql SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
BEGIN
  -- Only increment when payment_status changes to 'completed'
  IF NEW.payment_status = 'completed' AND (OLD.payment_status IS NULL OR OLD.payment_status != 'completed') THEN
    UPDATE competitions
    SET entries_sold = entries_sold + NEW.ticket_count
    WHERE id = NEW.competition_id;
  END IF;
  
  RETURN NEW;
END;
$$;


--
-- Name: handle_new_user(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.handle_new_user() RETURNS trigger
    LANGUAGE plpgsql SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
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
$$;


--
-- Name: has_role(uuid, public.app_role); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.has_role(_user_id uuid, _role public.app_role) RETURNS boolean
    LANGUAGE sql STABLE SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
  SELECT EXISTS (
    SELECT 1
    FROM public.user_roles
    WHERE user_id = _user_id
      AND role = _role
  )
$$;


--
-- Name: increment_entries_sold(uuid, integer); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.increment_entries_sold(p_competition_id uuid, p_count integer DEFAULT 1) RETURNS void
    LANGUAGE plpgsql SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
BEGIN
  UPDATE competitions
  SET entries_sold = entries_sold + p_count
  WHERE id = p_competition_id;
END;
$$;


--
-- Name: player_board_auto_draw(uuid); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.player_board_auto_draw(p_competition_id uuid) RETURNS jsonb
    LANGUAGE plpgsql SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
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
$$;


--
-- Name: process_instant_win(uuid, uuid, uuid); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.process_instant_win(p_entry_id uuid, p_competition_id uuid, p_user_id uuid) RETURNS jsonb
    LANGUAGE plpgsql SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
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
$$;


--
-- Name: set_competition_slug(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.set_competition_slug() RETURNS trigger
    LANGUAGE plpgsql
    SET search_path TO 'public'
    AS $$
BEGIN
  IF NEW.slug IS NULL OR NEW.slug = '' THEN
    NEW.slug := public.generate_unique_competition_slug(NEW.title, NEW.id);
  END IF;
  RETURN NEW;
END;
$$;


--
-- Name: slugify(text); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.slugify(p_text text) RETURNS text
    LANGUAGE sql IMMUTABLE
    SET search_path TO 'public'
    AS $$
  SELECT trim(both '-' from regexp_replace(
    regexp_replace(lower(coalesce(p_text, '')), '[^a-z0-9]+', '-', 'g'),
    '-+', '-', 'g'
  ));
$$;


--
-- Name: update_updated_at_column(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.update_updated_at_column() RETURNS trigger
    LANGUAGE plpgsql
    SET search_path TO 'public'
    AS $$
BEGIN
  NEW.updated_at = now();
  RETURN NEW;
END;
$$;


--
-- Name: upload_winner_id_document(uuid, text); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.upload_winner_id_document(p_winner_id uuid, p_file_path text) RETURNS void
    LANGUAGE plpgsql SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
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
$$;


--
-- Name: validate_promo_code(text); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.validate_promo_code(p_code text) RETURNS TABLE(code text, discount_type text, discount_value numeric, excluded_competition_ids text[])
    LANGUAGE plpgsql STABLE SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
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
$$;


--
-- Name: validate_quiz_answer(uuid, text); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.validate_quiz_answer(p_competition_id uuid, p_user_answer text) RETURNS boolean
    LANGUAGE plpgsql SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
BEGIN
  RETURN EXISTS (
    SELECT 1 FROM competitions 
    WHERE id = p_competition_id 
    AND LOWER(TRIM(quiz_answer)) = LOWER(TRIM(p_user_answer))
  );
END;
$$;


--
-- Name: verify_ticket_in_draw(uuid, integer); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.verify_ticket_in_draw(p_audit_id uuid, p_ticket_number integer) RETURNS jsonb
    LANGUAGE plpgsql STABLE SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
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
$$;


--
-- Name: announcement_bar; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.announcement_bar (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    is_active boolean DEFAULT false NOT NULL,
    message text DEFAULT ''::text NOT NULL,
    promo_code text,
    link_url text,
    link_text text,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    updated_at timestamp with time zone DEFAULT now() NOT NULL
);


--
-- Name: competitions_public; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.competitions_public WITH (security_invoker='on') AS
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
   FROM public.competitions;


--
-- Name: draw_audits; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.draw_audits (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    competition_id uuid NOT NULL,
    draw_performed_at timestamp with time zone DEFAULT now() NOT NULL,
    performed_by_admin_id uuid NOT NULL,
    total_paid_entries integer NOT NULL,
    total_postal_entries integer NOT NULL,
    total_tickets integer NOT NULL,
    random_seed text NOT NULL,
    winning_ticket_number integer NOT NULL,
    winner_type text NOT NULL,
    winner_entry_id uuid NOT NULL,
    winner_name text NOT NULL,
    winner_email text NOT NULL,
    video_timestamp text,
    audit_hash text NOT NULL,
    is_published boolean DEFAULT false NOT NULL,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    CONSTRAINT draw_audits_winner_type_check CHECK ((winner_type = ANY (ARRAY['paid'::text, 'postal'::text])))
);


--
-- Name: draw_audits_public; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.draw_audits_public WITH (security_invoker='on') AS
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
   FROM public.draw_audits
  WHERE (is_published = true);


--
-- Name: email_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.email_logs (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    recipient_email text NOT NULL,
    recipient_user_id uuid,
    email_type text NOT NULL,
    subject text NOT NULL,
    sent_at timestamp with time zone DEFAULT now() NOT NULL,
    status text DEFAULT 'sent'::text NOT NULL,
    metadata jsonb DEFAULT '{}'::jsonb
);


--
-- Name: entries; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.entries (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    user_id uuid NOT NULL,
    competition_id uuid NOT NULL,
    ticket_count integer DEFAULT 1 NOT NULL,
    amount_paid numeric(10,2) DEFAULT 0 NOT NULL,
    payment_status text DEFAULT 'pending'::text NOT NULL,
    payment_intent_id text,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    ticket_start_number integer,
    CONSTRAINT entries_payment_status_check CHECK ((payment_status = ANY (ARRAY['pending'::text, 'completed'::text, 'failed'::text, 'refunded'::text])))
);


--
-- Name: entry_tickets; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.entry_tickets (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    competition_id uuid NOT NULL,
    entry_id uuid,
    postal_entry_id uuid,
    user_id uuid,
    ticket_number integer NOT NULL,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    CONSTRAINT entry_tickets_source_chk CHECK (((entry_id IS NOT NULL) OR (postal_entry_id IS NOT NULL)))
);


--
-- Name: influencers; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.influencers (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    name text NOT NULL,
    email text,
    social_handle text,
    commission_rate numeric DEFAULT 10 NOT NULL,
    commission_type text DEFAULT 'percentage'::text NOT NULL,
    promo_code_id uuid,
    total_sales numeric DEFAULT 0 NOT NULL,
    total_commission numeric DEFAULT 0 NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    notes text,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    updated_at timestamp with time zone DEFAULT now() NOT NULL
);


--
-- Name: instant_win_results; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.instant_win_results (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    competition_id uuid NOT NULL,
    entry_id uuid NOT NULL,
    user_id uuid NOT NULL,
    prize_id uuid,
    is_winner boolean DEFAULT false NOT NULL,
    revealed boolean DEFAULT false NOT NULL,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    fulfillment_status text DEFAULT 'pending'::text NOT NULL
);


--
-- Name: orders; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.orders (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    order_number text NOT NULL,
    user_id uuid NOT NULL,
    amount numeric NOT NULL,
    currency text DEFAULT 'GBP'::text NOT NULL,
    status text DEFAULT 'created'::text NOT NULL,
    cashflows_payment_job_reference text,
    cashflows_payment_reference text,
    cashflows_last_status text,
    cashflows_action_url text,
    entry_ids text[] DEFAULT '{}'::text[],
    competition_ids text[] DEFAULT '{}'::text[],
    metadata jsonb DEFAULT '{}'::jsonb,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    updated_at timestamp with time zone DEFAULT now() NOT NULL,
    promo_code text,
    influencer_id uuid
);


--
-- Name: postal_entries; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.postal_entries (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    competition_id uuid NOT NULL,
    full_name text NOT NULL,
    email text NOT NULL,
    address_line1 text NOT NULL,
    address_line2 text,
    city text NOT NULL,
    postcode text NOT NULL,
    country text DEFAULT 'United Kingdom'::text NOT NULL,
    ticket_count integer DEFAULT 1 NOT NULL,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    added_by_admin_id uuid NOT NULL,
    notes text,
    ticket_start_number integer
);


--
-- Name: COLUMN postal_entries.ticket_start_number; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.postal_entries.ticket_start_number IS 'Sequential allocation at processing time, identical to paid entries; legacy rows retain null and draw fallback numbering.';


--
-- Name: profiles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.profiles (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    user_id uuid NOT NULL,
    email text NOT NULL,
    full_name text,
    avatar_url text,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    updated_at timestamp with time zone DEFAULT now() NOT NULL,
    marketing_opt_in boolean DEFAULT false NOT NULL,
    phone text,
    address_line1 text,
    address_line2 text,
    city text,
    postcode text,
    country text DEFAULT 'United Kingdom'::text
);


--
-- Name: promo_codes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.promo_codes (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    code text NOT NULL,
    discount_type text DEFAULT 'percentage'::text NOT NULL,
    discount_value numeric NOT NULL,
    max_uses integer,
    times_used integer DEFAULT 0 NOT NULL,
    min_order_amount numeric DEFAULT 0,
    valid_from timestamp with time zone DEFAULT now() NOT NULL,
    valid_until timestamp with time zone,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    updated_at timestamp with time zone DEFAULT now() NOT NULL,
    excluded_competition_ids text[] DEFAULT '{}'::text[]
);


--
-- Name: public_winners; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.public_winners WITH (security_barrier='true', security_invoker='true') AS
 SELECT id,
    winner_name,
    prize_description,
    won_at,
    announced,
    competition_id,
    winner_image_url
   FROM public.winners
  WHERE (announced = true);


--
-- Name: site_credits; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.site_credits (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    user_id uuid NOT NULL,
    amount numeric NOT NULL,
    type text DEFAULT 'grant'::text NOT NULL,
    reason text,
    order_id uuid,
    granted_by uuid,
    expires_at timestamp with time zone,
    created_at timestamp with time zone DEFAULT now() NOT NULL
);


--
-- Name: stats_cache; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.stats_cache (
    key text NOT NULL,
    value jsonb NOT NULL,
    refreshed_at timestamp with time zone DEFAULT now() NOT NULL
);


--
-- Name: user_limits; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.user_limits (
    user_id uuid NOT NULL,
    daily_limit numeric,
    weekly_limit numeric,
    monthly_limit numeric,
    cool_off_until timestamp with time zone,
    self_excluded_until timestamp with time zone,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    updated_at timestamp with time zone DEFAULT now() NOT NULL
);


--
-- Name: user_roles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.user_roles (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    user_id uuid NOT NULL,
    role public.app_role DEFAULT 'user'::public.app_role NOT NULL,
    created_at timestamp with time zone DEFAULT now() NOT NULL
);


--
-- Name: webhook_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.webhook_logs (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    source text DEFAULT 'cashflows'::text NOT NULL,
    event_type text,
    payload jsonb,
    processing_result text,
    processing_error text,
    created_at timestamp with time zone DEFAULT now() NOT NULL
);


--
-- Name: announcement_bar announcement_bar_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.announcement_bar
    ADD CONSTRAINT announcement_bar_pkey PRIMARY KEY (id);


--
-- Name: competition_spaces competition_spaces_competition_id_space_number_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.competition_spaces
    ADD CONSTRAINT competition_spaces_competition_id_space_number_key UNIQUE (competition_id, space_number);


--
-- Name: competition_spaces competition_spaces_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.competition_spaces
    ADD CONSTRAINT competition_spaces_pkey PRIMARY KEY (id);


--
-- Name: competitions competitions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.competitions
    ADD CONSTRAINT competitions_pkey PRIMARY KEY (id);


--
-- Name: draw_audits draw_audits_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.draw_audits
    ADD CONSTRAINT draw_audits_pkey PRIMARY KEY (id);


--
-- Name: email_logs email_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.email_logs
    ADD CONSTRAINT email_logs_pkey PRIMARY KEY (id);


--
-- Name: entries entries_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.entries
    ADD CONSTRAINT entries_pkey PRIMARY KEY (id);


--
-- Name: entry_tickets entry_tickets_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.entry_tickets
    ADD CONSTRAINT entry_tickets_pkey PRIMARY KEY (id);


--
-- Name: entry_tickets entry_tickets_unique_number; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.entry_tickets
    ADD CONSTRAINT entry_tickets_unique_number UNIQUE (competition_id, ticket_number);


--
-- Name: influencers influencers_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.influencers
    ADD CONSTRAINT influencers_pkey PRIMARY KEY (id);


--
-- Name: instant_win_prizes instant_win_prizes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.instant_win_prizes
    ADD CONSTRAINT instant_win_prizes_pkey PRIMARY KEY (id);


--
-- Name: instant_win_results instant_win_results_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.instant_win_results
    ADD CONSTRAINT instant_win_results_pkey PRIMARY KEY (id);


--
-- Name: orders orders_order_number_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.orders
    ADD CONSTRAINT orders_order_number_key UNIQUE (order_number);


--
-- Name: orders orders_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.orders
    ADD CONSTRAINT orders_pkey PRIMARY KEY (id);


--
-- Name: postal_entries postal_entries_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.postal_entries
    ADD CONSTRAINT postal_entries_pkey PRIMARY KEY (id);


--
-- Name: profiles profiles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.profiles
    ADD CONSTRAINT profiles_pkey PRIMARY KEY (id);


--
-- Name: profiles profiles_user_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.profiles
    ADD CONSTRAINT profiles_user_id_key UNIQUE (user_id);


--
-- Name: promo_codes promo_codes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.promo_codes
    ADD CONSTRAINT promo_codes_pkey PRIMARY KEY (id);


--
-- Name: site_credits site_credits_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.site_credits
    ADD CONSTRAINT site_credits_pkey PRIMARY KEY (id);


--
-- Name: stats_cache stats_cache_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stats_cache
    ADD CONSTRAINT stats_cache_pkey PRIMARY KEY (key);


--
-- Name: promo_codes unique_promo_code; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.promo_codes
    ADD CONSTRAINT unique_promo_code UNIQUE (code);


--
-- Name: user_limits user_limits_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_limits
    ADD CONSTRAINT user_limits_pkey PRIMARY KEY (user_id);


--
-- Name: user_roles user_roles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_roles
    ADD CONSTRAINT user_roles_pkey PRIMARY KEY (id);


--
-- Name: user_roles user_roles_user_id_role_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_roles
    ADD CONSTRAINT user_roles_user_id_role_key UNIQUE (user_id, role);


--
-- Name: webhook_logs webhook_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.webhook_logs
    ADD CONSTRAINT webhook_logs_pkey PRIMARY KEY (id);


--
-- Name: winners winners_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.winners
    ADD CONSTRAINT winners_pkey PRIMARY KEY (id);


--
-- Name: competitions_slug_key; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX competitions_slug_key ON public.competitions USING btree (slug);


--
-- Name: idx_competitions_scheduled_start; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_competitions_scheduled_start ON public.competitions USING btree (scheduled_start_at) WHERE (status = 'scheduled'::text);


--
-- Name: idx_competitions_status; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_competitions_status ON public.competitions USING btree (status);


--
-- Name: idx_competitions_status_draw; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_competitions_status_draw ON public.competitions USING btree (status, draw_date);


--
-- Name: idx_entries_competition; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_entries_competition ON public.entries USING btree (competition_id);


--
-- Name: idx_entries_competition_status; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_entries_competition_status ON public.entries USING btree (competition_id, payment_status);


--
-- Name: idx_entries_completed_competition; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_entries_completed_competition ON public.entries USING btree (competition_id) WHERE (payment_status = 'completed'::text);


--
-- Name: idx_entries_completed_created_at; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_entries_completed_created_at ON public.entries USING btree (created_at DESC) WHERE (payment_status = 'completed'::text);


--
-- Name: idx_entries_completed_user_created; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_entries_completed_user_created ON public.entries USING btree (user_id, created_at DESC) WHERE (payment_status = 'completed'::text);


--
-- Name: idx_entries_unique_ticket_start; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_entries_unique_ticket_start ON public.entries USING btree (competition_id, ticket_start_number) WHERE ((ticket_start_number IS NOT NULL) AND (payment_status = 'completed'::text));


--
-- Name: idx_entries_user; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_entries_user ON public.entries USING btree (user_id);


--
-- Name: idx_entries_user_competition; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_entries_user_competition ON public.entries USING btree (user_id, competition_id);


--
-- Name: idx_entry_tickets_comp_number; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_entry_tickets_comp_number ON public.entry_tickets USING btree (competition_id, ticket_number);


--
-- Name: idx_entry_tickets_entry; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_entry_tickets_entry ON public.entry_tickets USING btree (entry_id);


--
-- Name: idx_entry_tickets_postal; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_entry_tickets_postal ON public.entry_tickets USING btree (postal_entry_id);


--
-- Name: idx_entry_tickets_user; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_entry_tickets_user ON public.entry_tickets USING btree (user_id);


--
-- Name: idx_iw_results_winner_prize; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_iw_results_winner_prize ON public.instant_win_results USING btree (competition_id, prize_id) WHERE (is_winner = true);


--
-- Name: idx_iwp_competition; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_iwp_competition ON public.instant_win_prizes USING btree (competition_id);


--
-- Name: idx_iwp_competition_ticket; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_iwp_competition_ticket ON public.instant_win_prizes USING btree (competition_id, winning_ticket_number) WHERE (winning_ticket_number IS NOT NULL);


--
-- Name: idx_iwp_competition_tier; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_iwp_competition_tier ON public.instant_win_prizes USING btree (competition_id, prize_tier);


--
-- Name: idx_iwr_competition; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_iwr_competition ON public.instant_win_results USING btree (competition_id);


--
-- Name: idx_iwr_entry; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_iwr_entry ON public.instant_win_results USING btree (entry_id);


--
-- Name: idx_iwr_prize; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_iwr_prize ON public.instant_win_results USING btree (prize_id) WHERE (prize_id IS NOT NULL);


--
-- Name: idx_iwr_user; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_iwr_user ON public.instant_win_results USING btree (user_id);


--
-- Name: idx_iwr_user_winner; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_iwr_user_winner ON public.instant_win_results USING btree (user_id) WHERE (is_winner = true);


--
-- Name: idx_iwr_winner_created_at; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_iwr_winner_created_at ON public.instant_win_results USING btree (created_at DESC) WHERE (is_winner = true);


--
-- Name: idx_orders_cashflows_ref; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_orders_cashflows_ref ON public.orders USING btree (cashflows_payment_job_reference);


--
-- Name: idx_orders_order_number; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_orders_order_number ON public.orders USING btree (order_number);


--
-- Name: idx_orders_status_created_at; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_orders_status_created_at ON public.orders USING btree (status, created_at DESC);


--
-- Name: idx_orders_user_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_orders_user_id ON public.orders USING btree (user_id);


--
-- Name: idx_orders_user_status_created; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_orders_user_status_created ON public.orders USING btree (user_id, status, created_at DESC);


--
-- Name: idx_postal_competition; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_postal_competition ON public.postal_entries USING btree (competition_id);


--
-- Name: idx_profiles_created_at; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_profiles_created_at ON public.profiles USING btree (created_at DESC);


--
-- Name: idx_site_credits_expires_at; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_site_credits_expires_at ON public.site_credits USING btree (expires_at);


--
-- Name: idx_site_credits_user_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_site_credits_user_id ON public.site_credits USING btree (user_id);


--
-- Name: idx_site_credits_user_type; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_site_credits_user_type ON public.site_credits USING btree (user_id, type);


--
-- Name: idx_webhook_logs_errors_created; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_webhook_logs_errors_created ON public.webhook_logs USING btree (created_at DESC) WHERE (processing_error IS NOT NULL);


--
-- Name: idx_webhook_logs_source; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_webhook_logs_source ON public.webhook_logs USING btree (source);


--
-- Name: idx_winners_announced; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_winners_announced ON public.winners USING btree (competition_id) WHERE (announced = true);


--
-- Name: idx_winners_competition; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_winners_competition ON public.winners USING btree (competition_id);


--
-- Name: idx_winners_user; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_winners_user ON public.winners USING btree (user_id);


--
-- Name: entries aa_shared_entry_limit; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER aa_shared_entry_limit BEFORE INSERT OR UPDATE ON public.entries FOR EACH ROW EXECUTE FUNCTION public.enforce_shared_entry_limit();


--
-- Name: orders aa_shared_order_limit; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER aa_shared_order_limit BEFORE INSERT OR UPDATE ON public.orders FOR EACH ROW EXECUTE FUNCTION public.enforce_order_entry_limit();


--
-- Name: postal_entries aa_shared_postal_limit; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER aa_shared_postal_limit BEFORE INSERT OR UPDATE ON public.postal_entries FOR EACH ROW EXECUTE FUNCTION public.enforce_shared_entry_limit();


--
-- Name: postal_entries ab_assign_postal_ticket_numbers; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER ab_assign_postal_ticket_numbers BEFORE INSERT ON public.postal_entries FOR EACH ROW EXECUTE FUNCTION public.assign_postal_ticket_numbers();


--
-- Name: entries assign_ticket_numbers_trigger; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER assign_ticket_numbers_trigger BEFORE INSERT OR UPDATE ON public.entries FOR EACH ROW EXECUTE FUNCTION public.assign_ticket_numbers();


--
-- Name: competitions check_sold_out_trigger; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER check_sold_out_trigger BEFORE UPDATE OF entries_sold ON public.competitions FOR EACH ROW EXECUTE FUNCTION public.check_competition_sold_out();


--
-- Name: instant_win_results guard_instant_win_results_update_trg; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER guard_instant_win_results_update_trg BEFORE UPDATE ON public.instant_win_results FOR EACH ROW EXECUTE FUNCTION public.guard_instant_win_results_update();


--
-- Name: user_limits guard_user_limits_update; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER guard_user_limits_update BEFORE UPDATE ON public.user_limits FOR EACH ROW EXECUTE FUNCTION public.guard_user_limits_update();


--
-- Name: winners guard_winners_update_trg; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER guard_winners_update_trg BEFORE UPDATE ON public.winners FOR EACH ROW EXECUTE FUNCTION public.guard_winners_update();


--
-- Name: entries on_entry_payment_completed; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER on_entry_payment_completed AFTER INSERT OR UPDATE OF payment_status ON public.entries FOR EACH ROW EXECUTE FUNCTION public.handle_entry_payment_completed();


--
-- Name: entries trg_allocate_random_tickets; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_allocate_random_tickets AFTER INSERT OR UPDATE ON public.entries FOR EACH ROW EXECUTE FUNCTION public.allocate_random_tickets_for_entry();


--
-- Name: postal_entries trg_allocate_random_tickets_postal; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_allocate_random_tickets_postal AFTER INSERT ON public.postal_entries FOR EACH ROW EXECUTE FUNCTION public.allocate_random_tickets_for_postal();


--
-- Name: entries trg_assign_ticket_numbers; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_assign_ticket_numbers BEFORE INSERT OR UPDATE OF payment_status ON public.entries FOR EACH ROW EXECUTE FUNCTION public.assign_ticket_numbers();


--
-- Name: entries trg_enforce_one_free_entry_per_user; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_enforce_one_free_entry_per_user BEFORE INSERT ON public.entries FOR EACH ROW EXECUTE FUNCTION public.enforce_one_free_entry_per_user();


--
-- Name: competitions trg_force_sequential_instant_win; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_force_sequential_instant_win BEFORE INSERT OR UPDATE ON public.competitions FOR EACH ROW EXECUTE FUNCTION public.force_sequential_for_instant_win();


--
-- Name: competitions trg_set_competition_slug; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_set_competition_slug BEFORE INSERT ON public.competitions FOR EACH ROW EXECUTE FUNCTION public.set_competition_slug();


--
-- Name: competitions update_competitions_updated_at; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER update_competitions_updated_at BEFORE UPDATE ON public.competitions FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


--
-- Name: influencers update_influencers_updated_at; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER update_influencers_updated_at BEFORE UPDATE ON public.influencers FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


--
-- Name: orders update_orders_updated_at; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER update_orders_updated_at BEFORE UPDATE ON public.orders FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


--
-- Name: profiles update_profiles_updated_at; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER update_profiles_updated_at BEFORE UPDATE ON public.profiles FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


--
-- Name: promo_codes update_promo_codes_updated_at; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER update_promo_codes_updated_at BEFORE UPDATE ON public.promo_codes FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


--
-- Name: user_limits update_user_limits_updated_at; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER update_user_limits_updated_at BEFORE UPDATE ON public.user_limits FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


--
-- Name: competition_spaces competition_spaces_competition_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.competition_spaces
    ADD CONSTRAINT competition_spaces_competition_id_fkey FOREIGN KEY (competition_id) REFERENCES public.competitions(id) ON DELETE CASCADE;


--
-- Name: competition_spaces competition_spaces_entry_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.competition_spaces
    ADD CONSTRAINT competition_spaces_entry_id_fkey FOREIGN KEY (entry_id) REFERENCES public.entries(id);


--
-- Name: competition_spaces competition_spaces_order_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.competition_spaces
    ADD CONSTRAINT competition_spaces_order_id_fkey FOREIGN KEY (order_id) REFERENCES public.orders(id);


--
-- Name: draw_audits draw_audits_competition_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.draw_audits
    ADD CONSTRAINT draw_audits_competition_id_fkey FOREIGN KEY (competition_id) REFERENCES public.competitions(id) ON DELETE CASCADE;


--
-- Name: email_logs email_logs_recipient_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.email_logs
    ADD CONSTRAINT email_logs_recipient_user_id_fkey FOREIGN KEY (recipient_user_id) REFERENCES auth.users(id) ON DELETE SET NULL;


--
-- Name: entries entries_competition_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.entries
    ADD CONSTRAINT entries_competition_id_fkey FOREIGN KEY (competition_id) REFERENCES public.competitions(id) ON DELETE CASCADE;


--
-- Name: entries entries_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.entries
    ADD CONSTRAINT entries_user_id_fkey FOREIGN KEY (user_id) REFERENCES auth.users(id) ON DELETE CASCADE;


--
-- Name: entry_tickets entry_tickets_competition_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.entry_tickets
    ADD CONSTRAINT entry_tickets_competition_id_fkey FOREIGN KEY (competition_id) REFERENCES public.competitions(id) ON DELETE CASCADE;


--
-- Name: entry_tickets entry_tickets_entry_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.entry_tickets
    ADD CONSTRAINT entry_tickets_entry_id_fkey FOREIGN KEY (entry_id) REFERENCES public.entries(id) ON DELETE CASCADE;


--
-- Name: entry_tickets entry_tickets_postal_entry_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.entry_tickets
    ADD CONSTRAINT entry_tickets_postal_entry_id_fkey FOREIGN KEY (postal_entry_id) REFERENCES public.postal_entries(id) ON DELETE CASCADE;


--
-- Name: influencers influencers_promo_code_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.influencers
    ADD CONSTRAINT influencers_promo_code_id_fkey FOREIGN KEY (promo_code_id) REFERENCES public.promo_codes(id);


--
-- Name: instant_win_prizes instant_win_prizes_competition_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.instant_win_prizes
    ADD CONSTRAINT instant_win_prizes_competition_id_fkey FOREIGN KEY (competition_id) REFERENCES public.competitions(id) ON DELETE CASCADE;


--
-- Name: instant_win_results instant_win_results_competition_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.instant_win_results
    ADD CONSTRAINT instant_win_results_competition_id_fkey FOREIGN KEY (competition_id) REFERENCES public.competitions(id) ON DELETE CASCADE;


--
-- Name: instant_win_results instant_win_results_entry_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.instant_win_results
    ADD CONSTRAINT instant_win_results_entry_id_fkey FOREIGN KEY (entry_id) REFERENCES public.entries(id) ON DELETE CASCADE;


--
-- Name: instant_win_results instant_win_results_prize_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.instant_win_results
    ADD CONSTRAINT instant_win_results_prize_id_fkey FOREIGN KEY (prize_id) REFERENCES public.instant_win_prizes(id);


--
-- Name: orders orders_influencer_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.orders
    ADD CONSTRAINT orders_influencer_id_fkey FOREIGN KEY (influencer_id) REFERENCES public.influencers(id);


--
-- Name: postal_entries postal_entries_competition_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.postal_entries
    ADD CONSTRAINT postal_entries_competition_id_fkey FOREIGN KEY (competition_id) REFERENCES public.competitions(id) ON DELETE CASCADE;


--
-- Name: profiles profiles_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.profiles
    ADD CONSTRAINT profiles_user_id_fkey FOREIGN KEY (user_id) REFERENCES auth.users(id) ON DELETE CASCADE;


--
-- Name: site_credits site_credits_order_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.site_credits
    ADD CONSTRAINT site_credits_order_id_fkey FOREIGN KEY (order_id) REFERENCES public.orders(id);


--
-- Name: user_roles user_roles_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_roles
    ADD CONSTRAINT user_roles_user_id_fkey FOREIGN KEY (user_id) REFERENCES auth.users(id) ON DELETE CASCADE;


--
-- Name: winners winners_competition_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.winners
    ADD CONSTRAINT winners_competition_id_fkey FOREIGN KEY (competition_id) REFERENCES public.competitions(id) ON DELETE CASCADE;


--
-- Name: winners winners_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.winners
    ADD CONSTRAINT winners_user_id_fkey FOREIGN KEY (user_id) REFERENCES auth.users(id) ON DELETE SET NULL;


--
-- Name: entries Admins can manage all entries; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY "Admins can manage all entries" ON public.entries USING (public.has_role(auth.uid(), 'admin'::public.app_role));


--
-- Name: orders Admins can manage all orders; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY "Admins can manage all orders" ON public.orders USING (public.has_role(auth.uid(), 'admin'::public.app_role));


--
-- Name: site_credits Admins can manage all site credits; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY "Admins can manage all site credits" ON public.site_credits USING (public.has_role(auth.uid(), 'admin'::public.app_role));


--
-- Name: announcement_bar Admins can manage announcement bar; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY "Admins can manage announcement bar" ON public.announcement_bar USING (public.has_role(auth.uid(), 'admin'::public.app_role));


--
-- Name: competition_spaces Admins can manage competition spaces; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY "Admins can manage competition spaces" ON public.competition_spaces USING (public.has_role(auth.uid(), 'admin'::public.app_role));


--
-- Name: competitions Admins can manage competitions; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY "Admins can manage competitions" ON public.competitions USING (public.has_role(auth.uid(), 'admin'::public.app_role));


--
-- Name: draw_audits Admins can manage draw audits; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY "Admins can manage draw audits" ON public.draw_audits USING (public.has_role(auth.uid(), 'admin'::public.app_role));


--
-- Name: email_logs Admins can manage email logs; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY "Admins can manage email logs" ON public.email_logs USING (public.has_role(auth.uid(), 'admin'::public.app_role));


--
-- Name: influencers Admins can manage influencers; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY "Admins can manage influencers" ON public.influencers USING (public.has_role(auth.uid(), 'admin'::public.app_role));


--
-- Name: instant_win_prizes Admins can manage instant win prizes; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY "Admins can manage instant win prizes" ON public.instant_win_prizes USING (public.has_role(auth.uid(), 'admin'::public.app_role));


--
-- Name: instant_win_results Admins can manage instant win results; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY "Admins can manage instant win results" ON public.instant_win_results USING (public.has_role(auth.uid(), 'admin'::public.app_role));


--
-- Name: postal_entries Admins can manage postal entries; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY "Admins can manage postal entries" ON public.postal_entries USING (public.has_role(auth.uid(), 'admin'::public.app_role));


--
-- Name: promo_codes Admins can manage promo codes; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY "Admins can manage promo codes" ON public.promo_codes USING (public.has_role(auth.uid(), 'admin'::public.app_role));


--
-- Name: user_roles Admins can manage roles; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY "Admins can manage roles" ON public.user_roles TO authenticated USING (public.has_role(auth.uid(), 'admin'::public.app_role)) WITH CHECK (public.has_role(auth.uid(), 'admin'::public.app_role));


--
-- Name: webhook_logs Admins can manage webhook logs; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY "Admins can manage webhook logs" ON public.webhook_logs USING (public.has_role(auth.uid(), 'admin'::public.app_role));


--
-- Name: winners Admins can manage winners; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY "Admins can manage winners" ON public.winners USING (public.has_role(auth.uid(), 'admin'::public.app_role));


--
-- Name: profiles Admins can update all profiles; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY "Admins can update all profiles" ON public.profiles FOR UPDATE USING (public.has_role(auth.uid(), 'admin'::public.app_role));


--
-- Name: email_logs Admins can view all email logs; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY "Admins can view all email logs" ON public.email_logs FOR SELECT USING (public.has_role(auth.uid(), 'admin'::public.app_role));


--
-- Name: entries Admins can view all entries; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY "Admins can view all entries" ON public.entries FOR SELECT USING (public.has_role(auth.uid(), 'admin'::public.app_role));


--
-- Name: orders Admins can view all orders; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY "Admins can view all orders" ON public.orders FOR SELECT USING (public.has_role(auth.uid(), 'admin'::public.app_role));


--
-- Name: profiles Admins can view all profiles; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY "Admins can view all profiles" ON public.profiles FOR SELECT USING (public.has_role(auth.uid(), 'admin'::public.app_role));


--
-- Name: user_roles Admins can view all roles; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY "Admins can view all roles" ON public.user_roles FOR SELECT USING (public.has_role(auth.uid(), 'admin'::public.app_role));


--
-- Name: entry_tickets Admins can view all tickets; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY "Admins can view all tickets" ON public.entry_tickets FOR SELECT TO authenticated USING (public.has_role(auth.uid(), 'admin'::public.app_role));


--
-- Name: winners Admins can view all winners; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY "Admins can view all winners" ON public.winners FOR SELECT USING (public.has_role(auth.uid(), 'admin'::public.app_role));


--
-- Name: webhook_logs Admins can view webhook logs; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY "Admins can view webhook logs" ON public.webhook_logs FOR SELECT USING (public.has_role(auth.uid(), 'admin'::public.app_role));


--
-- Name: user_limits Admins manage all limits; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY "Admins manage all limits" ON public.user_limits USING (public.has_role(auth.uid(), 'admin'::public.app_role));


--
-- Name: announcement_bar Anyone can view announcement bar; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY "Anyone can view announcement bar" ON public.announcement_bar FOR SELECT USING (true);


--
-- Name: winners Public can view announced winners (non-sensitive); Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY "Public can view announced winners (non-sensitive)" ON public.winners FOR SELECT TO authenticated, anon USING ((announced = true));


--
-- Name: competition_spaces Public can view non-PII space fields; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY "Public can view non-PII space fields" ON public.competition_spaces FOR SELECT TO authenticated, anon USING (true);


--
-- Name: competitions Public can view non-sensitive competition fields; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY "Public can view non-sensitive competition fields" ON public.competitions FOR SELECT TO authenticated, anon USING (true);


--
-- Name: instant_win_prizes Public can view non-sensitive instant win prize fields; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY "Public can view non-sensitive instant win prize fields" ON public.instant_win_prizes FOR SELECT TO authenticated, anon USING (true);


--
-- Name: profiles Users can update their own profile; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY "Users can update their own profile" ON public.profiles FOR UPDATE USING ((auth.uid() = user_id));


--
-- Name: site_credits Users can view their own credits; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY "Users can view their own credits" ON public.site_credits FOR SELECT USING ((auth.uid() = user_id));


--
-- Name: entries Users can view their own entries; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY "Users can view their own entries" ON public.entries FOR SELECT USING ((auth.uid() = user_id));


--
-- Name: orders Users can view their own orders; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY "Users can view their own orders" ON public.orders FOR SELECT USING ((auth.uid() = user_id));


--
-- Name: profiles Users can view their own profile; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY "Users can view their own profile" ON public.profiles FOR SELECT USING ((auth.uid() = user_id));


--
-- Name: instant_win_results Users can view their own results; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY "Users can view their own results" ON public.instant_win_results FOR SELECT USING ((auth.uid() = user_id));


--
-- Name: user_roles Users can view their own roles; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY "Users can view their own roles" ON public.user_roles FOR SELECT USING ((auth.uid() = user_id));


--
-- Name: entry_tickets Users can view their own tickets; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY "Users can view their own tickets" ON public.entry_tickets FOR SELECT TO authenticated USING ((user_id = auth.uid()));


--
-- Name: user_limits Users insert own limits; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY "Users insert own limits" ON public.user_limits FOR INSERT TO authenticated WITH CHECK ((auth.uid() = user_id));


--
-- Name: user_limits Users update own limits; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY "Users update own limits" ON public.user_limits FOR UPDATE TO authenticated USING ((auth.uid() = user_id)) WITH CHECK ((auth.uid() = user_id));


--
-- Name: user_limits Users view own limits; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY "Users view own limits" ON public.user_limits FOR SELECT TO authenticated USING ((auth.uid() = user_id));


--
-- Name: winners Winners can update their own records for verification; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY "Winners can update their own records for verification" ON public.winners FOR UPDATE USING ((auth.uid() = user_id)) WITH CHECK ((auth.uid() = user_id));


--
-- Name: announcement_bar; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.announcement_bar ENABLE ROW LEVEL SECURITY;

--
-- Name: competition_spaces; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.competition_spaces ENABLE ROW LEVEL SECURITY;

--
-- Name: competitions; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.competitions ENABLE ROW LEVEL SECURITY;

--
-- Name: draw_audits; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.draw_audits ENABLE ROW LEVEL SECURITY;

--
-- Name: email_logs; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.email_logs ENABLE ROW LEVEL SECURITY;

--
-- Name: entries; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.entries ENABLE ROW LEVEL SECURITY;

--
-- Name: entry_tickets; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.entry_tickets ENABLE ROW LEVEL SECURITY;

--
-- Name: influencers; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.influencers ENABLE ROW LEVEL SECURITY;

--
-- Name: instant_win_prizes; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.instant_win_prizes ENABLE ROW LEVEL SECURITY;

--
-- Name: instant_win_results; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.instant_win_results ENABLE ROW LEVEL SECURITY;

--
-- Name: orders; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.orders ENABLE ROW LEVEL SECURITY;

--
-- Name: postal_entries; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.postal_entries ENABLE ROW LEVEL SECURITY;

--
-- Name: profiles; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.profiles ENABLE ROW LEVEL SECURITY;

--
-- Name: promo_codes; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.promo_codes ENABLE ROW LEVEL SECURITY;

--
-- Name: site_credits; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.site_credits ENABLE ROW LEVEL SECURITY;

--
-- Name: stats_cache; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.stats_cache ENABLE ROW LEVEL SECURITY;

--
-- Name: user_limits; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.user_limits ENABLE ROW LEVEL SECURITY;

--
-- Name: user_roles; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.user_roles ENABLE ROW LEVEL SECURITY;

--
-- Name: webhook_logs; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.webhook_logs ENABLE ROW LEVEL SECURITY;

--
-- Name: winners; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.winners ENABLE ROW LEVEL SECURITY;

--
-- PostgreSQL database dump complete
--



-- ===== auth.users triggers calling public functions =====
CREATE TRIGGER on_auth_user_created AFTER INSERT ON auth.users FOR EACH ROW EXECUTE FUNCTION handle_new_user();

-- ===== storage buckets =====
-- bucket competition-images public=t file_size_limit= allowed_mime=
-- bucket winner-id-documents public=f file_size_limit= allowed_mime=
-- bucket email-assets public=t file_size_limit= allowed_mime=
-- ===== storage.objects policies =====
CREATE POLICY "Anyone can view competition images" ON storage.objects AS PERMISSIVE FOR SELECT TO public USING ((bucket_id = 'competition-images'::text));
CREATE POLICY "Admins can upload competition images" ON storage.objects AS PERMISSIVE FOR INSERT TO public WITH CHECK (((bucket_id = 'competition-images'::text) AND has_role(auth.uid(), 'admin'::app_role)));
CREATE POLICY "Admins can update competition images" ON storage.objects AS PERMISSIVE FOR UPDATE TO public USING (((bucket_id = 'competition-images'::text) AND has_role(auth.uid(), 'admin'::app_role)));
CREATE POLICY "Admins can delete competition images" ON storage.objects AS PERMISSIVE FOR DELETE TO public USING (((bucket_id = 'competition-images'::text) AND has_role(auth.uid(), 'admin'::app_role)));
CREATE POLICY "Winners can upload their own ID" ON storage.objects AS PERMISSIVE FOR INSERT TO public WITH CHECK (((bucket_id = 'winner-id-documents'::text) AND ((auth.uid())::text = (storage.foldername(name))[1])));
CREATE POLICY "Winners can view their own ID" ON storage.objects AS PERMISSIVE FOR SELECT TO public USING (((bucket_id = 'winner-id-documents'::text) AND ((auth.uid())::text = (storage.foldername(name))[1])));
CREATE POLICY "Admins can view all winner ID documents" ON storage.objects AS PERMISSIVE FOR SELECT TO public USING (((bucket_id = 'winner-id-documents'::text) AND (EXISTS ( SELECT 1
   FROM user_roles
  WHERE ((user_roles.user_id = auth.uid()) AND (user_roles.role = 'admin'::app_role))))));
CREATE POLICY "Admins can delete winner ID documents" ON storage.objects AS PERMISSIVE FOR DELETE TO public USING (((bucket_id = 'winner-id-documents'::text) AND (EXISTS ( SELECT 1
   FROM user_roles
  WHERE ((user_roles.user_id = auth.uid()) AND (user_roles.role = 'admin'::app_role))))));
CREATE POLICY "Admins can upload winner ID documents" ON storage.objects AS PERMISSIVE FOR INSERT TO authenticated WITH CHECK (((bucket_id = 'winner-id-documents'::text) AND has_role(auth.uid(), 'admin'::app_role)));
CREATE POLICY "Admins can read winner ID documents" ON storage.objects AS PERMISSIVE FOR SELECT TO authenticated USING (((bucket_id = 'winner-id-documents'::text) AND has_role(auth.uid(), 'admin'::app_role)));
CREATE POLICY "Winners can upload their own ID documents" ON storage.objects AS PERMISSIVE FOR INSERT TO authenticated WITH CHECK (((bucket_id = 'winner-id-documents'::text) AND ((storage.foldername(name))[1] = (auth.uid())::text)));
CREATE POLICY "Admins can update winner ID documents" ON storage.objects AS PERMISSIVE FOR UPDATE TO authenticated USING (((bucket_id = 'winner-id-documents'::text) AND has_role(auth.uid(), 'admin'::app_role))) WITH CHECK (((bucket_id = 'winner-id-documents'::text) AND has_role(auth.uid(), 'admin'::app_role)));

-- ===== pg_cron jobs (verbatim, secrets [REDACTED], project URL genericised) =====
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
