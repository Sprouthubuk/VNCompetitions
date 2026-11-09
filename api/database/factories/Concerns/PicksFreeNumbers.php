<?php

namespace Database\Factories\Concerns;

use App\Models\Competition;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Factories follow the production rule: numbers are random, picked with
 * random_int() from the numbers the comp hasn't used yet. Never sequential.
 */
trait PicksFreeNumbers
{
    /**
     * Numbers already handed out in this process but maybe not inserted yet:
     * count(n)->create() makes every model before saving any of them.
     *
     * @var array<string, array<int, true>>
     */
    private static array $handedOut = [];

    /**
     * @param  string  $table  tickets or instant_win_prizes
     * @param  string  $column  number or ticket_number
     */
    protected static function randomFreeNumber(string $competitionId, string $table, string $column): int
    {
        $max = (int) Competition::query()->whereKey($competitionId)->value('max_tickets');
        $key = "{$table}:{$competitionId}";

        $used = self::$handedOut[$key] ?? [];
        foreach (DB::table($table)->where('competition_id', $competitionId)->pluck($column) as $n) {
            $used[(int) $n] = true;
        }

        if (count($used) >= $max) {
            throw new RuntimeException("No free numbers left in competition {$competitionId}.");
        }

        do {
            $number = random_int(1, $max);
        } while (isset($used[$number]));

        self::$handedOut[$key][$number] = true;

        return $number;
    }
}
