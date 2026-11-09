<?php

use App\Enums\CompetitionStatus;
use App\Enums\PrizeTier;
use App\Models\Competition;
use App\Models\CompetitionBundle;
use App\Models\CompetitionOffer;
use App\Models\InstantWinPrize;
use App\Models\InstantWinPrizeLine;
use App\Models\Ticket;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;

/** Keys every comp has, in contract order (docs/API_CONTRACT.md). */
const COMP_KEYS = [
    'slug', 'title', 'status', 'brand', 'kind', 'type', 'category', 'price', 'was', 'sold', 'max', 'drawAt', 'drawMode',
    'description', 'cashAlternative', 'featured', 'free', 'image', 'bundles', 'buyX', 'multiplier', 'endPrize',
];

/** Extra keys instant-win comps have. */
const INSTANT_KEYS = ['game', 'instantCount', 'instantRemaining', 'instantPrizes'];

function boardComp(): Competition
{
    $comp = Competition::factory()->onSale('0.49', '0.29')->create([
        'slug' => 'pick-your-board', 'title' => 'Pick your board', 'brand' => 'Unicorn', 'kind' => 'board',
        'category' => 'boards', 'max_tickets' => 999, 'draw_at' => CarbonImmutable::parse('2026-11-11T21:30:00Z'),
        'description' => 'Winner chooses a board.', 'featured' => true, 'cash_alternative' => '120.00',
    ]);
    CompetitionBundle::factory()->for($comp)->create(['tickets' => 10, 'price' => '2.50', 'sort' => 0]);
    CompetitionBundle::factory()->for($comp)->create(['tickets' => 25, 'price' => '5.75', 'tag' => 'Popular', 'sort' => 1]);
    CompetitionOffer::factory()->for($comp)->buyXGetY(10, 2)->create();

    return $comp;
}

/** An instant comp with locked prizes: 2 of 3 darts left, 4 of 5 credits left. */
function instantComp(): Competition
{
    $comp = Competition::factory()->instant()->create([
        'slug' => 'target-takeover', 'max_tickets' => 500, 'end_prize' => 'Target Omni home set-up',
        'prizes_locked_at' => now(),
    ]);
    $darts = InstantWinPrizeLine::factory()->for($comp)->create(['name' => 'Target darts', 'tier' => PrizeTier::Darts, 'quantity' => 3, 'sort' => 0]);
    $credit = InstantWinPrizeLine::factory()->for($comp)->credit('1.00')->create(['name' => '£1 site credit', 'quantity' => 5, 'sort' => 1]);

    InstantWinPrize::factory()->count(3)->for($darts, 'line')->create();
    InstantWinPrize::factory()->count(5)->for($credit, 'line')->create();

    foreach ([$darts, $credit] as $line) {
        $ticket = Ticket::factory()->for($comp)->create();
        InstantWinPrize::query()->where('instant_win_prize_line_id', $line->id)->whereNull('won_at')->firstOrFail()
            ->delete();
        InstantWinPrize::factory()->for($line, 'line')->wonBy($ticket)->create();
    }
    CompetitionOffer::factory()->for($comp)->multiplier(50, 2)->create();

    return $comp;
}

describe('GET /api/v1/competitions', function () {
    it('returns a standard comp in exactly the contract shape', function () {
        $comp = boardComp();
        Ticket::factory()->count(3)->for($comp)->create();
        Ticket::factory()->for($comp)->held()->create();
        Ticket::factory()->for($comp)->postal()->create();

        $json = $this->getJson('/api/v1/competitions')->assertOk()->json();

        expect(array_keys($json))->toBe(['data'])
            ->and($json['data'])->toHaveCount(1)
            ->and(array_keys($json['data'][0]))->toBe(COMP_KEYS)
            ->and($json['data'][0])->toBe([
                'slug' => 'pick-your-board',
                'title' => 'Pick your board',
                'status' => 'live',
                'brand' => 'Unicorn',
                'kind' => 'board',
                'type' => 'standard',
                'category' => 'Boards',
                'price' => 0.29,
                'was' => 0.49,
                'sold' => 5, // sold + held + postal: everything that uses capacity
                'max' => 999,
                'drawAt' => '2026-11-11T21:30:00Z',
                'drawMode' => 'live',
                'description' => 'Winner chooses a board.',
                'cashAlternative' => 120,
                'featured' => true,
                'free' => false,
                'image' => null,
                'bundles' => [['tickets' => 10, 'price' => 2.5], ['tickets' => 25, 'price' => 5.75, 'tag' => 'Popular']],
                'buyX' => ['buy' => 10, 'free' => 2],
                'multiplier' => null,
                'endPrize' => null,
            ]);
    });

    it('returns an instant comp with the public prize ledger', function () {
        instantComp();

        $comp = $this->getJson('/api/v1/competitions')->assertOk()->json('data.0');

        expect(array_keys($comp))->toBe([...COMP_KEYS, ...INSTANT_KEYS])
            ->and(Arr::only($comp, ['type', 'category', 'drawMode', 'game', 'instantCount', 'instantRemaining', 'endPrize', 'multiplier', 'sold']))->toEqual([
                'type' => 'instant',
                'category' => 'Instant win',
                'drawMode' => 'auto',
                'endPrize' => 'Target Omni home set-up',
                'multiplier' => ['from' => 50, 'times' => 2],
                'sold' => 2,
                'game' => 'wheel',
                'instantCount' => 8,
                'instantRemaining' => 6,
            ])
            ->and($comp['instantPrizes'])->toBe([
                ['name' => 'Target darts', 'tier' => 'Darts', 'left' => 2, 'total' => 3],
                ['name' => '£1 site credit', 'tier' => 'Credit', 'left' => 4, 'total' => 5],
            ]);
    });

    it('never exposes instant-win prize numbers', function () {
        $comp = instantComp();
        $numbers = InstantWinPrize::query()->where('competition_id', $comp->id)->whereNull('won_at')->pluck('ticket_number');

        foreach (['/api/v1/competitions', '/api/v1/competitions/target-takeover'] as $url) {
            $response = $this->getJson($url)->assertOk();
            $body = $response->getContent();

            expect($body)->not->toContain('ticket_number')
                ->and($body)->not->toContain('ticketNumber')
                ->and($body)->not->toContain('seed');

            $prizes = $url === '/api/v1/competitions' ? $response->json('data.0.instantPrizes') : $response->json('data.instantPrizes');
            foreach ($prizes as $prize) {
                expect(array_keys($prize))->toBe(['name', 'tier', 'left', 'total']);
            }

            // No unwon number appears anywhere as a value.
            $values = Arr::flatten($response->json());
            foreach ($numbers as $n) {
                expect(in_array($n, $values, true) && ! in_array($n, [2, 3, 4, 5, 6, 8, 500], true))->toBeFalse();
            }
        }
    });

    it('lists live and sold-out comps, soonest draw first', function () {
        Competition::factory()->create(['slug' => 'later', 'draw_at' => now()->addDays(9)]);
        Competition::factory()->create(['slug' => 'sooner', 'draw_at' => now()->addDays(2)]);
        Competition::factory()->status(CompetitionStatus::SoldOut)->create(['slug' => 'sold-out', 'draw_at' => now()->addDays(5)]);
        foreach ([CompetitionStatus::Draft, CompetitionStatus::ComingSoon, CompetitionStatus::Cancelled, CompetitionStatus::Closed, CompetitionStatus::Drawn] as $status) {
            Competition::factory()->status($status)->create(['slug' => $status->value, 'draw_at' => now()->addDay()]);
        }

        $data = $this->getJson('/api/v1/competitions')->assertOk()->json('data');

        expect(array_column($data, 'slug'))->toBe(['sooner', 'sold-out', 'later'])
            ->and(array_column($data, 'status'))->toBe(['live', 'sold_out', 'live']);
    });

    it('drops a sold-out comp from the list once it is drawn', function () {
        $comp = Competition::factory()->status(CompetitionStatus::SoldOut)->create(['slug' => 'sold-out']);
        expect($this->getJson('/api/v1/competitions')->json('data.*.slug'))->toBe(['sold-out']);

        $comp->update(['status' => CompetitionStatus::Drawn]);

        expect($this->getJson('/api/v1/competitions')->json('data'))->toBe([])
            ->and($this->getJson('/api/v1/competitions/sold-out')->assertOk()->json('data.status'))->toBe('drawn');
    });

    it('marks free comps and drops was when there is no sale', function () {
        Competition::factory()->free()->autoDraw()->create(['slug' => 'freebie']);

        $comp = $this->getJson('/api/v1/competitions')->json('data.0');

        expect($comp['free'])->toBeTrue()
            ->and($comp['price'])->toBe(0)
            ->and($comp['was'])->toBeNull()
            ->and($comp['drawMode'])->toBe('auto')
            ->and($comp['bundles'])->toBe([]);
    });

    it('sends cache headers and answers JSON', function () {
        boardComp();

        $this->get('/api/v1/competitions')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertHeader('Cache-Control', 'max-age=15, public');
    });
});

describe('GET /api/v1/competitions/{slug}', function () {
    it('returns one comp in the same shape', function () {
        boardComp();

        $list = $this->getJson('/api/v1/competitions')->json('data.0');
        $one = $this->getJson('/api/v1/competitions/pick-your-board')->assertOk()->json();

        expect(array_keys($one))->toBe(['data'])->and($one['data'])->toBe($list);
    });

    it('shows sold-out, closed and drawn comps so old links still work', function (CompetitionStatus $status) {
        Competition::factory()->status($status)->create(['slug' => 'old-comp']);

        $this->getJson('/api/v1/competitions/old-comp')->assertOk()->assertJsonPath('data.slug', 'old-comp');
    })->with([CompetitionStatus::SoldOut, CompetitionStatus::Closed, CompetitionStatus::Drawn]);

    it('hides draft, coming-soon and cancelled comps', function (CompetitionStatus $status) {
        Competition::factory()->status($status)->create(['slug' => 'hidden-comp']);

        $this->get('/api/v1/competitions/hidden-comp')->assertNotFound()->assertJson(['message' => 'Competition not found.']);
    })->with([CompetitionStatus::Draft, CompetitionStatus::ComingSoon, CompetitionStatus::Cancelled]);

    it('404s an unknown slug as JSON', function () {
        $this->get('/api/v1/competitions/nope', ['Accept' => 'text/html'])
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/json');
    });
});

describe('caching', function () {
    it('caches responses and busts them on a ticket sale and an admin save', function (string $store) {
        config(['cache.default' => $store]);
        $comp = boardComp();

        expect($this->getJson('/api/v1/competitions/pick-your-board')->json('data.sold'))->toBe(0);

        // A write that skips model events is served from cache...
        DB::table('competitions')->where('id', $comp->id)->update(['title' => 'Changed behind the cache']);
        expect($this->getJson('/api/v1/competitions/pick-your-board')->json('data.title'))->toBe('Pick your board');

        // ...until a ticket sale busts it,
        Ticket::factory()->for($comp)->create();
        expect($this->getJson('/api/v1/competitions/pick-your-board')->json('data'))
            ->sold->toBe(1)
            ->title->toBe('Changed behind the cache');

        // and an admin save busts the list too.
        $this->getJson('/api/v1/competitions');
        $comp->refresh()->update(['title' => 'Saved in admin']);
        expect($this->getJson('/api/v1/competitions')->json('data.0.title'))->toBe('Saved in admin');

        // Coming soon hides it immediately.
        $comp->update(['status' => CompetitionStatus::ComingSoon]);
        $this->getJson('/api/v1/competitions/pick-your-board')->assertNotFound();
        expect($this->getJson('/api/v1/competitions')->json('data'))->toBe([]);
    })->with(['tagged (array, like Redis)' => 'array', 'untagged (database)' => 'database']);

    it('forgets the old slug when a comp is renamed', function () {
        $comp = boardComp();
        $this->getJson('/api/v1/competitions/pick-your-board')->assertOk();

        $comp->update(['slug' => 'pick-a-board']);

        $this->getJson('/api/v1/competitions/pick-your-board')->assertNotFound();
        $this->getJson('/api/v1/competitions/pick-a-board')->assertOk();
    });
});
