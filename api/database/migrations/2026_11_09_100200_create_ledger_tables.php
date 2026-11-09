<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /** @var list<string> */
    private array $appendOnly = ['wallet_transactions', 'limit_events', 'admin_audit_log'];

    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION reject_ledger_change() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION '% is append-only: % is not allowed', TG_TABLE_NAME, TG_OP
                    USING ERRCODE = 'restrict_violation';
            END $$;
        SQL);

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 10, 2);
            $table->enum('type', ['prize', 'refund', 'goodwill', 'spend', 'expiry', 'reversal']);
            $table->decimal('balance_after', 10, 2);
            $table->timestampTz('expires_at')->nullable();
            $table->nullableUuidMorphs('source');
            $table->string('note')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE wallet_transactions
                ADD CONSTRAINT wallet_transactions_amount_non_zero CHECK (amount <> 0),
                ADD CONSTRAINT wallet_transactions_balance_non_negative CHECK (balance_after >= 0),
                ADD CONSTRAINT wallet_transactions_sign_matches_type CHECK (
                    (type IN ('prize', 'refund', 'goodwill') AND amount > 0)
                    OR (type IN ('spend', 'expiry') AND amount < 0)
                    OR type = 'reversal'
                )
        SQL);

        Schema::create('responsible_limits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('daily_limit', 10, 2)->nullable();
            $table->decimal('weekly_limit', 10, 2)->nullable();
            $table->decimal('monthly_limit', 10, 2)->nullable();
            // Increases wait 24 hours; decreases apply immediately.
            $table->decimal('pending_daily_limit', 10, 2)->nullable();
            $table->decimal('pending_weekly_limit', 10, 2)->nullable();
            $table->decimal('pending_monthly_limit', 10, 2)->nullable();
            $table->timestampTz('pending_effective_at')->nullable();
            $table->timestampTz('cool_off_until')->nullable();
            $table->timestampTz('excluded_until')->nullable();
            $table->timestampsTz();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE responsible_limits
                ADD CONSTRAINT responsible_limits_non_negative CHECK (
                    COALESCE(daily_limit, 0) >= 0 AND COALESCE(weekly_limit, 0) >= 0 AND COALESCE(monthly_limit, 0) >= 0
                    AND COALESCE(pending_daily_limit, 0) >= 0 AND COALESCE(pending_weekly_limit, 0) >= 0
                    AND COALESCE(pending_monthly_limit, 0) >= 0
                )
        SQL);

        Schema::create('limit_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->restrictOnDelete();
            $table->enum('type', ['limits_changed', 'increase_scheduled', 'increase_applied', 'cool_off', 'self_exclusion']);
            $table->jsonb('before')->nullable();
            $table->jsonb('after')->nullable();
            $table->timestampTz('effective_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
        });

        Schema::create('admin_audit_log', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('admin_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('action', 64);
            $table->string('subject_type')->nullable();
            $table->uuid('subject_id')->nullable();
            $table->jsonb('before')->nullable();
            $table->jsonb('after')->nullable();
            $table->string('reason')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id']);
            $table->index('created_at');
        });

        foreach ($this->appendOnly as $table) {
            DB::unprepared("CREATE TRIGGER {$table}_append_only BEFORE UPDATE OR DELETE ON {$table} FOR EACH ROW EXECUTE FUNCTION reject_ledger_change()");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_log');
        Schema::dropIfExists('limit_events');
        Schema::dropIfExists('responsible_limits');
        Schema::dropIfExists('wallet_transactions');
        DB::unprepared('DROP FUNCTION IF EXISTS reject_ledger_change()');
    }
};
