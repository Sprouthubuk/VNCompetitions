<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Enum value lists are written out here (not read from App\Enums) so this migration
 * keeps producing the same schema if an enum gains a case later. Add new values
 * with a new migration.
 */
return new class () extends Migration {
    public function up(): void
    {
        DB::statement('CREATE UNIQUE INDEX users_email_lower_unique ON users (lower(email))');

        Schema::create('competitions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('brand');
            $table->enum('kind', ['darts', 'board', 'ring', 'box', 'voucher']);
            $table->enum('type', ['standard', 'instant'])->default('standard');
            $table->string('category');
            $table->text('description');
            $table->enum('status', ['draft', 'coming_soon', 'live', 'sold_out', 'closed', 'drawn', 'cancelled'])
                ->default('draft');
            $table->boolean('is_free')->default(false);
            $table->decimal('price', 10, 2);
            $table->decimal('sale_price', 10, 2)->nullable();
            $table->decimal('cash_alternative', 10, 2)->nullable();
            $table->integer('max_tickets');
            $table->timestampTz('draw_at');
            $table->boolean('auto_draw')->default(false);
            $table->boolean('featured')->default(false);
            $table->integer('sort')->default(0);
            $table->string('image_path')->nullable();
            $table->string('end_prize')->nullable();
            $table->enum('game', ['classic', 'dartboard', 'wheel'])->nullable();
            $table->timestampTz('prizes_locked_at')->nullable();
            $table->char('prize_commitment', 64)->nullable();
            $table->decimal('cost_price', 10, 2)->default(0);
            $table->boolean('prize_purchased')->default(false);
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->timestampsTz();

            $table->index(['status', 'draw_at']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE competitions
                ADD CONSTRAINT competitions_max_tickets_positive CHECK (max_tickets > 0),
                ADD CONSTRAINT competitions_price_non_negative CHECK (price >= 0),
                ADD CONSTRAINT competitions_sale_price_below_price CHECK (sale_price IS NULL OR (sale_price >= 0 AND sale_price < price)),
                ADD CONSTRAINT competitions_free_has_no_price CHECK (NOT is_free OR (price = 0 AND sale_price IS NULL)),
                ADD CONSTRAINT competitions_cash_alternative_non_negative CHECK (cash_alternative IS NULL OR cash_alternative >= 0),
                ADD CONSTRAINT competitions_cost_price_non_negative CHECK (cost_price >= 0),
                ADD CONSTRAINT competitions_instant_has_game CHECK (type <> 'instant' OR game IS NOT NULL),
                ADD CONSTRAINT competitions_commitment_is_sha256 CHECK (prize_commitment IS NULL OR prize_commitment ~ '^[0-9a-f]{64}$')
        SQL);

        Schema::create('competition_bundles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('competition_id')->constrained()->cascadeOnDelete();
            $table->integer('tickets');
            $table->decimal('price', 10, 2);
            $table->string('tag', 32)->nullable();
            $table->integer('sort')->default(0);
            $table->timestampsTz();

            $table->unique(['competition_id', 'tickets']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE competition_bundles
                ADD CONSTRAINT competition_bundles_tickets_min CHECK (tickets >= 2),
                ADD CONSTRAINT competition_bundles_price_positive CHECK (price > 0)
        SQL);

        Schema::create('competition_offers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('competition_id')->constrained()->cascadeOnDelete();
            $table->enum('kind', ['buy_x_get_y', 'multiplier']);
            $table->integer('buy')->nullable();
            $table->integer('free')->nullable();
            $table->integer('from_tickets')->nullable();
            $table->integer('times')->nullable();
            $table->timestampsTz();

            $table->unique(['competition_id', 'kind']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE competition_offers
                ADD CONSTRAINT competition_offers_shape CHECK (
                    (kind = 'buy_x_get_y' AND buy > 0 AND free > 0 AND from_tickets IS NULL AND times IS NULL)
                    OR (kind = 'multiplier' AND from_tickets > 0 AND times >= 2 AND buy IS NULL AND free IS NULL)
                )
        SQL);

        // A prize line is what staff configure (name, tier, quantity). At go-live it is
        // expanded into one instant_win_prizes row per unit, each with a random number.
        Schema::create('instant_win_prize_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('competition_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('tier', ['premium', 'darts', 'accessories', 'credit']);
            $table->integer('quantity');
            $table->decimal('credit_amount', 10, 2)->nullable();
            $table->integer('sort')->default(0);
            $table->timestampsTz();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE instant_win_prize_lines
                ADD CONSTRAINT instant_win_prize_lines_quantity_positive CHECK (quantity > 0),
                ADD CONSTRAINT instant_win_prize_lines_credit_shape CHECK (
                    (tier = 'credit' AND credit_amount > 0) OR (tier <> 'credit' AND credit_amount IS NULL)
                )
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('instant_win_prize_lines');
        Schema::dropIfExists('competition_offers');
        Schema::dropIfExists('competition_bundles');
        Schema::dropIfExists('competitions');
        DB::statement('DROP INDEX IF EXISTS users_email_lower_unique');
    }
};
