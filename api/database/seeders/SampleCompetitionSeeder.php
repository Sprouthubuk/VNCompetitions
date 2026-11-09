<?php

namespace Database\Seeders;

use App\Enums\Category;
use App\Enums\CompetitionStatus;
use App\Enums\OfferKind;
use App\Enums\OrderStatus;
use App\Enums\PrizeTier;
use App\Enums\TicketStatus;
use App\Enums\WalletTransactionType;
use App\Models\Competition;
use App\Models\InstantWinPrize;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Seeds the 8 sample comps from web/src/data/comps.ts with their bundles,
 * offers and instant prizes, plus enough sample customers, paid orders and
 * tickets that `sold` and the prize ledger match the design preview.
 *
 * Every number (tickets and prize units) is random, as in production.
 *
 * Runs without dev dependencies (Forge deploys with `composer install --no-dev`):
 * no factories, no fake(). Safe to re-run: comps are matched by slug with
 * updateOrCreate, bundles and offers are synced, and tickets, prizes and
 * orders are only created the first time a comp is seeded. A re-run never
 * changes an existing comp's status, so admin changes to it are kept.
 */
class SampleCompetitionSeeder extends Seeder
{
    /** The sample customers who "bought" the seeded tickets (customer1…customer40@sample.vaultnine.test). */
    private const CUSTOMER_NAMES = [
        'Craig Baker', 'Toby Shears', 'McKenzie Vetier', 'Jamie Guest', 'Ben Carter', 'Leigh Picton', 'Dean Bush', 'Robert Pickford',
        'Sam Holloway', 'Chris Dunmore', 'Gary Whitlock', 'Paul Hensley', 'Mark Ellison', 'Steve Rowntree', 'Lee Marsden', 'Danny Okafor',
        'Ryan Kettle', 'Josh Tranter', 'Kieran Doyle', 'Adam Pryce', 'Nathan Greaves', 'Luke Ashdown', 'Tom Brannigan', 'Matt Seddon',
        'Wayne Colley', 'Darren Pugh', 'Karl Ingham', 'Neil Fothergill', 'Jordan Mace', 'Callum Reeve', 'Ashley Booth', 'Shaun Tilley',
        'Kerry Lomas', 'Gemma Hartley', 'Lisa Cranston', 'Jodie Mercer', 'Hayley Dunn', 'Sophie Rigby', 'Emma Pollard', 'Rachel Stott',
    ];

    /** @var list<string> */
    private array $customers = [];

    public function run(): void
    {
        /** @var list<array<string, mixed>> $comps */
        $comps = require __DIR__.'/data/sample-competitions.php';

        $this->customers = $this->sampleCustomers();

        foreach ($comps as $sort => $data) {
            $created = DB::transaction(fn () => $this->seedCompetition($data, $sort));
            $this->command->line(($created ? '  seeded ' : '  updated ').$data['slug']);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return bool true when the comp was created, false when it already existed
     */
    private function seedCompetition(array $data, int $sort): bool
    {
        $was = isset($data['was']) ? self::money($data['was']) : null;
        $current = self::money($data['price']);
        $instant = $data['type'] === 'instant';
        $isNew = ! Competition::query()->where('slug', $data['slug'])->exists();

        $details = [
            'title' => $data['title'],
            'brand' => $data['brand'],
            'kind' => $data['kind'],
            'type' => $data['type'],
            'category' => Category::fromLabel($data['category']),
            'description' => $data['description'],
            'price' => $was ?? $current,
            'sale_price' => $was === null ? null : $current,
            'cash_alternative' => isset($data['cashAlternative']) ? self::money($data['cashAlternative']) : null,
            'max_tickets' => $data['max'],
            'draw_at' => CarbonImmutable::parse($data['drawAt']),
            'auto_draw' => $data['drawMode'] === 'auto',
            'featured' => $data['featured'] ?? false,
            'sort' => $sort,
            'end_prize' => $data['endPrize'] ?? null,
            'game' => $data['game'] ?? null,
        ];

        // Status and the prize lock are only set on create; a re-run leaves them alone.
        $comp = Competition::query()->updateOrCreate(
            ['slug' => $data['slug']],
            $isNew ? $details + ['status' => CompetitionStatus::Live, 'prizes_locked_at' => $instant ? now() : null] : $details,
        );

        // Bundles: matched by ticket count; ones no longer in the sample data are removed.
        $tickets = [];
        foreach ($data['bundles'] ?? [] as $i => $bundle) {
            $comp->bundles()->updateOrCreate(['tickets' => $bundle['tickets']], [
                'price' => self::money($bundle['price']),
                'tag' => $bundle['tag'] ?? null,
                'sort' => $i,
            ]);
            $tickets[] = $bundle['tickets'];
        }
        $comp->bundles()->whereNotIn('tickets', $tickets)->delete();

        // Offers: at most one of each kind.
        $offers = [
            OfferKind::BuyXGetY->value => isset($data['buyX']) ? ['buy' => $data['buyX']['buy'], 'free' => $data['buyX']['free']] : null,
            OfferKind::Multiplier->value => isset($data['multiplier']) ? ['from_tickets' => $data['multiplier']['from'], 'times' => $data['multiplier']['times']] : null,
        ];
        foreach ($offers as $kind => $values) {
            if ($values === null) {
                $comp->offers()->where('kind', $kind)->delete();
            } else {
                $comp->offers()->updateOrCreate(['kind' => $kind], $values);
            }
        }

        // Prize lines, prize units, orders and tickets only on create: prize numbers are
        // locked at launch, and re-seeding entries would double the tickets sold.
        if ($isNew) {
            $this->seedEntries($comp, $data['sold'], $data['instantPrizes'] ?? []);
        }

        return $isNew;
    }

    /**
     * Prize units get random numbers; `total - left` of each line are won by sold
     * tickets on those numbers. The other sold tickets get random numbers that
     * carry no prize (or they'd have won one).
     *
     * @param  list<array{name: string, left: int, total: int, tier: string}>  $prizes
     */
    private function seedEntries(Competition $comp, int $sold, array $prizes): void
    {
        $unitCount = array_sum(array_column($prizes, 'total'));
        $wonCount = $unitCount - array_sum(array_column($prizes, 'left'));
        if ($wonCount > $sold) {
            throw new RuntimeException("{$comp->slug}: {$wonCount} prizes won but only {$sold} tickets sold.");
        }

        $numbers = self::randomDistinct($comp->max_tickets, $unitCount + ($sold - $wonCount));
        $prizeNumbers = array_slice($numbers, 0, $unitCount);
        $plainNumbers = array_slice($numbers, $unitCount);

        // Prize lines and their units. $wins: ticket number => [unit id, line].
        $now = now();
        $units = [];
        $wins = [];
        $cursor = 0;
        foreach ($prizes as $i => $prize) {
            $tier = PrizeTier::fromLabel($prize['tier']);
            $line = $comp->prizeLines()->create([
                'name' => $prize['name'],
                'tier' => $tier,
                'quantity' => $prize['total'],
                'credit_amount' => $tier === PrizeTier::Credit ? self::creditAmount($prize['name']) : null,
                'sort' => $i,
            ]);

            for ($u = 0; $u < $prize['total']; $u++) {
                $number = $prizeNumbers[$cursor++];
                $id = (string) Str::uuid7();
                $units[] = [
                    'id' => $id, 'competition_id' => $comp->id, 'instant_win_prize_line_id' => $line->id,
                    'ticket_number' => $number, 'created_at' => $now, 'updated_at' => $now,
                ];
                if ($u < $prize['total'] - $prize['left']) {
                    $wins[$number] = ['unit' => $id, 'credit' => $line->credit_amount];
                }
            }
        }

        // Sold tickets: the winning numbers plus prize-free numbers, in random order,
        // grouped into paid orders from the sample customers.
        $ticketNumbers = self::shuffled(array_merge(array_keys($wins), $plainNumbers));

        $tickets = [];
        $results = [];
        $wonUnits = [];
        $credits = [];
        $price = $comp->currentPrice();
        foreach (self::chunkRandomly($ticketNumbers, 1, 40) as $chunk) {
            $userId = $this->customers[random_int(0, count($this->customers) - 1)];
            $itemId = $this->paidOrder($userId, $comp, count($chunk), $price);

            foreach ($chunk as $number) {
                $ticketId = (string) Str::uuid7();
                $tickets[] = [
                    'id' => $ticketId, 'competition_id' => $comp->id, 'number' => $number, 'status' => TicketStatus::Sold->value,
                    'order_item_id' => $itemId, 'user_id' => $userId, 'multiplier' => 1, 'created_at' => $now, 'updated_at' => $now,
                ];

                if ($comp->isInstant()) {
                    $win = $wins[$number] ?? null;
                    $results[] = [
                        'id' => (string) Str::uuid7(), 'ticket_id' => $ticketId, 'instant_win_prize_id' => $win['unit'] ?? null,
                        'revealed_at' => $now, 'created_at' => $now, 'updated_at' => $now,
                    ];
                    if ($win !== null) {
                        $wonUnits[$win['unit']] = $ticketId;
                        if ($win['credit'] !== null) {
                            $credits[] = ['user' => $userId, 'amount' => $win['credit'], 'unit' => $win['unit']];
                        }
                    }
                }
            }
        }

        foreach ($units as &$unit) {
            $ticketId = $wonUnits[$unit['id']] ?? null;
            $unit['won_by_ticket_id'] = $ticketId;
            $unit['won_at'] = $ticketId === null ? null : $now;
        }
        unset($unit);

        foreach (array_chunk($tickets, 1000) as $rows) {
            DB::table('tickets')->insert($rows);
        }
        foreach (array_chunk($units, 1000) as $rows) {
            DB::table('instant_win_prizes')->insert($rows);
        }
        foreach (array_chunk($results, 1000) as $rows) {
            DB::table('instant_win_results')->insert($rows);
        }
        $this->creditWallets($credits);
    }

    private function paidOrder(string $userId, Competition $comp, int $quantity, string $unitPrice): string
    {
        $now = now();
        $total = bcmul($unitPrice, (string) $quantity, 2);
        $orderId = (string) Str::uuid7();
        $itemId = (string) Str::uuid7();

        DB::table('orders')->insert([
            'id' => $orderId, 'user_id' => $userId, 'status' => OrderStatus::Paid->value,
            'subtotal' => $total, 'discount' => '0.00', 'credit_used' => '0.00', 'total' => $total,
            'payment_reference' => 'SEED-'.Str::upper(Str::random(16)), 'paid_at' => $now,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('order_items')->insert([
            'id' => $itemId, 'order_id' => $orderId, 'competition_id' => $comp->id, 'quantity' => $quantity,
            'bonus_tickets' => 0, 'unit_price' => $unitPrice, 'line_total' => $total,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        return $itemId;
    }

    /**
     * Credit prizes go to the wallet with a 30-day expiry, as the InstantWinService will.
     *
     * @param  list<array{user: string, amount: string, unit: string}>  $credits
     */
    private function creditWallets(array $credits): void
    {
        $now = now();
        $morph = (new InstantWinPrize())->getMorphClass();
        $balances = [];
        $rows = [];
        foreach ($credits as $credit) {
            $user = $credit['user'];
            $balances[$user] ??= (string) (DB::table('wallet_transactions')->where('user_id', $user)
                ->orderByDesc('created_at')->orderByDesc('id')->value('balance_after') ?? '0.00');
            $balances[$user] = bcadd($balances[$user], $credit['amount'], 2);
            $rows[] = [
                'id' => (string) Str::uuid7(), 'user_id' => $user, 'amount' => $credit['amount'],
                'type' => WalletTransactionType::Prize->value, 'balance_after' => $balances[$user],
                'expires_at' => $now->copy()->addDays(30), 'source_type' => $morph, 'source_id' => $credit['unit'],
                'note' => 'Instant win', 'created_at' => $now,
            ];
        }
        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('wallet_transactions')->insert($chunk);
        }
    }

    /**
     * @return list<string> user ids
     */
    private function sampleCustomers(): array
    {
        $password = Hash::make(Str::password(24)); // nobody can log in as a sample customer
        $ids = [];
        foreach (self::CUSTOMER_NAMES as $i => $name) {
            $n = $i + 1;
            $email = "customer{$n}@sample.vaultnine.test";
            $user = User::query()->where('email', $email)->first();

            if ($user === null) {
                $user = new User([
                    'name' => $name,
                    'email' => $email,
                    'password' => $password,
                    'date_of_birth' => CarbonImmutable::create(1960 + ($n * 7) % 40, 1 + $n % 12, 1 + $n % 28),
                    'country' => 'GB',
                ]);
                $user->forceFill(['email_verified_at' => now(), 'age_confirmed_at' => now()])->save();
            }

            $ids[] = $user->id;
        }

        return $ids;
    }

    /**
     * $count distinct numbers from 1..$max, in random order (partial Fisher-Yates with random_int).
     *
     * @return list<int>
     */
    private static function randomDistinct(int $max, int $count): array
    {
        if ($count > $max) {
            throw new RuntimeException("Can't pick {$count} distinct numbers from 1..{$max}.");
        }

        $pool = range(1, $max);
        for ($i = 0; $i < $count; $i++) {
            $j = random_int($i, $max - 1);
            [$pool[$i], $pool[$j]] = [$pool[$j], $pool[$i]];
        }

        return array_slice($pool, 0, $count);
    }

    /**
     * Fisher-Yates with random_int.
     *
     * @param  list<int>  $items
     * @return list<int>
     */
    private static function shuffled(array $items): array
    {
        for ($i = count($items) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        }

        return array_values($items);
    }

    /**
     * @param  list<int>  $items
     * @return list<list<int>>
     */
    private static function chunkRandomly(array $items, int $min, int $max): array
    {
        $chunks = [];
        while ($items !== []) {
            $chunks[] = array_splice($items, 0, random_int($min, $max));
        }

        return $chunks;
    }

    private static function money(int|float|string $pounds): string
    {
        return number_format((float) $pounds, 2, '.', '');
    }

    /** "50p site credit" => 0.50, "£1 Vault Nine site credit" => 1.00 */
    private static function creditAmount(string $name): string
    {
        if (preg_match('/£(\d+(?:\.\d{1,2})?)/u', $name, $m)) {
            return self::money($m[1]);
        }
        if (preg_match('/(\d+)p\b/', $name, $m)) {
            return self::money(((int) $m[1]) / 100);
        }

        throw new RuntimeException("Can't read a credit amount from \"{$name}\".");
    }
}
