<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('promo_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 64)->unique();
            $table->decimal('percent', 5, 2)->nullable();
            $table->decimal('fixed', 10, 2)->nullable();
            $table->decimal('min_order', 10, 2)->nullable();
            $table->integer('max_uses')->nullable();
            $table->integer('uses')->default(0);
            $table->timestampTz('expires_at')->nullable();
            $table->string('influencer')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE promo_codes
                ADD CONSTRAINT promo_codes_code_lower CHECK (code = lower(code) AND code <> ''),
                ADD CONSTRAINT promo_codes_one_discount CHECK ((percent IS NULL) <> (fixed IS NULL)),
                ADD CONSTRAINT promo_codes_percent_range CHECK (percent IS NULL OR (percent > 0 AND percent <= 100)),
                ADD CONSTRAINT promo_codes_fixed_positive CHECK (fixed IS NULL OR fixed > 0),
                ADD CONSTRAINT promo_codes_min_order_non_negative CHECK (min_order IS NULL OR min_order >= 0),
                ADD CONSTRAINT promo_codes_uses_within_max CHECK (uses >= 0 AND (max_uses IS NULL OR (max_uses > 0 AND uses <= max_uses)))
        SQL);

        Schema::create('promo_code_excluded_competitions', function (Blueprint $table) {
            $table->foreignUuid('promo_code_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('competition_id')->constrained()->cascadeOnDelete();
            $table->primary(['promo_code_id', 'competition_id']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->restrictOnDelete();
            $table->enum('status', ['pending', 'paid', 'failed', 'expired', 'refunded'])->default('pending');
            $table->decimal('subtotal', 10, 2);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('credit_used', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->string('payment_reference')->nullable()->unique();
            $table->foreignUuid('promo_code_id')->nullable()->constrained()->nullOnDelete();
            $table->timestampTz('held_until')->nullable();
            $table->timestampTz('paid_at')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'created_at']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE orders
                ADD CONSTRAINT orders_money_non_negative CHECK (subtotal >= 0 AND discount >= 0 AND credit_used >= 0 AND total >= 0),
                ADD CONSTRAINT orders_total_adds_up CHECK (total = subtotal - discount - credit_used),
                ADD CONSTRAINT orders_paid_has_paid_at CHECK (status <> 'paid' OR paid_at IS NOT NULL)
        SQL);

        Schema::create('order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('competition_id')->constrained()->restrictOnDelete();
            $table->integer('quantity');
            $table->integer('bonus_tickets')->default(0);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('line_total', 10, 2);
            $table->foreignUuid('bundle_id')->nullable()->constrained('competition_bundles')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['order_id', 'competition_id']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE order_items
                ADD CONSTRAINT order_items_quantity_positive CHECK (quantity > 0 AND bonus_tickets >= 0),
                ADD CONSTRAINT order_items_money_non_negative CHECK (unit_price >= 0 AND line_total >= 0)
        SQL);

        Schema::create('postal_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('competition_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->text('address');
            $table->string('postcode', 16)->nullable();
            $table->string('email')->nullable();
            $table->timestampTz('received_at');
            $table->foreignUuid('keyed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestampsTz();

            $table->index(['competition_id', 'postcode']);
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('competition_id')->constrained()->restrictOnDelete();
            $table->integer('number');
            $table->enum('status', ['held', 'sold', 'postal', 'free']);
            $table->foreignUuid('order_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUuid('postal_entry_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestampTz('held_until')->nullable();
            $table->integer('multiplier')->default(1);
            $table->timestampsTz();

            $table->unique(['competition_id', 'number']);
            $table->index('user_id');
            $table->index('order_item_id');
            $table->index(['status', 'held_until']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE tickets
                ADD CONSTRAINT tickets_number_positive CHECK (number >= 1),
                ADD CONSTRAINT tickets_multiplier_positive CHECK (multiplier >= 1),
                ADD CONSTRAINT tickets_status_shape CHECK (
                    (status = 'held' AND held_until IS NOT NULL AND order_item_id IS NOT NULL AND user_id IS NOT NULL)
                    OR (status = 'sold' AND held_until IS NULL AND order_item_id IS NOT NULL AND user_id IS NOT NULL)
                    OR (status = 'postal' AND held_until IS NULL AND postal_entry_id IS NOT NULL)
                    OR (status = 'free' AND held_until IS NULL AND user_id IS NOT NULL)
                )
        SQL);

        // Ticket and prize numbers must lie within 1..max_tickets of their competition,
        // and max_tickets can't be lowered below a number already in use.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION enforce_number_within_capacity() RETURNS trigger LANGUAGE plpgsql AS $$
            DECLARE
                cap integer;
                num integer;
            BEGIN
                -- TG_ARGV[0] names the number column: tickets.number or instant_win_prizes.ticket_number.
                num := (to_jsonb(NEW) ->> TG_ARGV[0])::integer;
                SELECT max_tickets INTO cap FROM competitions WHERE id = NEW.competition_id;
                IF num > cap THEN
                    RAISE EXCEPTION 'number % is outside 1..% for competition %', num, cap, NEW.competition_id
                        USING ERRCODE = 'check_violation';
                END IF;
                RETURN NEW;
            END $$;

            CREATE TRIGGER tickets_number_within_capacity
                BEFORE INSERT OR UPDATE OF number, competition_id ON tickets
                FOR EACH ROW EXECUTE FUNCTION enforce_number_within_capacity('number');

            CREATE OR REPLACE FUNCTION enforce_capacity_covers_numbers() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF NEW.max_tickets < OLD.max_tickets AND (
                    EXISTS (SELECT 1 FROM tickets WHERE competition_id = NEW.id AND number > NEW.max_tickets)
                    OR EXISTS (SELECT 1 FROM instant_win_prizes WHERE competition_id = NEW.id AND ticket_number > NEW.max_tickets)
                ) THEN
                    RAISE EXCEPTION 'max_tickets % is below a number already in use for competition %', NEW.max_tickets, NEW.id
                        USING ERRCODE = 'check_violation';
                END IF;
                RETURN NEW;
            END $$;
        SQL);

        // Never serialised publicly before it's won (see InstantWinPrize::$hidden).
        Schema::create('instant_win_prizes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('competition_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('instant_win_prize_line_id')->constrained()->cascadeOnDelete();
            $table->integer('ticket_number');
            $table->foreignUuid('won_by_ticket_id')->nullable()->unique()->constrained('tickets')->restrictOnDelete();
            $table->timestampTz('won_at')->nullable();
            $table->timestampsTz();

            $table->unique(['competition_id', 'ticket_number']);
            $table->index(['instant_win_prize_line_id', 'won_at']);
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE instant_win_prizes
                ADD CONSTRAINT instant_win_prizes_number_positive CHECK (ticket_number >= 1),
                ADD CONSTRAINT instant_win_prizes_won_shape CHECK ((won_by_ticket_id IS NULL) = (won_at IS NULL));

            CREATE TRIGGER instant_win_prizes_number_within_capacity
                BEFORE INSERT OR UPDATE OF ticket_number, competition_id ON instant_win_prizes
                FOR EACH ROW EXECUTE FUNCTION enforce_number_within_capacity('ticket_number');

            CREATE TRIGGER competitions_capacity_covers_numbers
                BEFORE UPDATE OF max_tickets ON competitions
                FOR EACH ROW EXECUTE FUNCTION enforce_capacity_covers_numbers();
        SQL);

        Schema::create('instant_win_results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('ticket_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignUuid('instant_win_prize_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->timestampTz('revealed_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('draws', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('competition_id')->constrained()->restrictOnDelete();
            $table->enum('kind', ['end_prize', 'standard']);
            $table->char('entries_hash', 64);
            $table->char('seed_commitment', 64);
            $table->string('seed')->nullable();
            $table->text('public_entropy')->nullable();
            $table->foreignUuid('winning_ticket_id')->nullable()->constrained('tickets')->restrictOnDelete();
            $table->timestampTz('drawn_at')->nullable();
            $table->foreignUuid('drawn_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->index('competition_id');
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE draws
                ADD CONSTRAINT draws_hashes_are_sha256 CHECK (entries_hash ~ '^[0-9a-f]{64}$' AND seed_commitment ~ '^[0-9a-f]{64}$');

            -- Append-only. Before the seed is revealed the draw may be completed
            -- (entropy, winner, seed); the commitment and entry hash never change.
            -- Once the seed is set the row is sealed. Rows are never deleted.
            CREATE OR REPLACE FUNCTION draws_append_only() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION 'draws are append-only' USING ERRCODE = 'restrict_violation';
                END IF;
                IF OLD.seed IS NOT NULL THEN
                    RAISE EXCEPTION 'draw % is sealed: its seed has been revealed', OLD.id USING ERRCODE = 'restrict_violation';
                END IF;
                IF NEW.competition_id <> OLD.competition_id OR NEW.kind <> OLD.kind
                    OR NEW.entries_hash <> OLD.entries_hash OR NEW.seed_commitment <> OLD.seed_commitment THEN
                    RAISE EXCEPTION 'draw % commitment fields are immutable', OLD.id USING ERRCODE = 'restrict_violation';
                END IF;
                RETURN NEW;
            END $$;

            CREATE TRIGGER draws_append_only
                BEFORE UPDATE OR DELETE ON draws
                FOR EACH ROW EXECUTE FUNCTION draws_append_only();
        SQL);

        Schema::create('winners', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('competition_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('ticket_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUuid('draw_id')->nullable()->constrained()->restrictOnDelete();
            // Snapshot of the winning number; also holds it for imported winners with no ticket row.
            $table->integer('ticket_number')->nullable();
            $table->string('prize_text');
            $table->string('display_name');
            $table->string('photo_path')->nullable();
            $table->timestampTz('announced_at')->nullable();
            $table->timestampTz('verified_at')->nullable();
            $table->timestampsTz();

            $table->index('announced_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('winners');
        Schema::dropIfExists('draws');
        DB::unprepared('DROP FUNCTION IF EXISTS draws_append_only()');
        Schema::dropIfExists('instant_win_results');
        Schema::dropIfExists('instant_win_prizes');
        Schema::dropIfExists('tickets');
        DB::unprepared('DROP TRIGGER IF EXISTS competitions_capacity_covers_numbers ON competitions');
        DB::unprepared('DROP FUNCTION IF EXISTS enforce_capacity_covers_numbers()');
        DB::unprepared('DROP FUNCTION IF EXISTS enforce_number_within_capacity()');
        Schema::dropIfExists('postal_entries');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('promo_code_excluded_competitions');
        Schema::dropIfExists('promo_codes');
    }
};
