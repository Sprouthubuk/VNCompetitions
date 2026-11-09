<?php

use App\Models\Competition;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Winner;
use Carbon\CarbonImmutable;

it('returns announced winners in exactly the contract shape', function () {
    $user = User::factory()->create(['name' => 'Craig Baker', 'email' => 'craig@example.com', 'phone' => '07700 900123']);
    Winner::factory()->create([
        'user_id' => $user->id, 'display_name' => 'Craig Baker', 'prize_text' => 'Star Wars Target Mandalorian 26g',
        'ticket_number' => 143, 'announced_at' => CarbonImmutable::parse('2026-11-08 21:30', 'Europe/London'),
    ]);

    $json = $this->getJson('/api/v1/winners')->assertOk()->json();

    expect($json)->toBe(['data' => [
        ['name' => 'Craig B.', 'prize' => 'Star Wars Target Mandalorian 26g', 'ticket' => '#143', 'date' => '08 Nov 2026'],
    ]]);

    $body = json_encode($json);
    expect($body)->not->toContain('Baker')->not->toContain('craig@')->not->toContain('07700');
});

it('takes the number from the winning ticket, or says live draw', function () {
    $comp = Competition::factory()->drawn()->create(['max_tickets' => 50]);
    $ticket = Ticket::factory()->for($comp)->create();
    Winner::factory()->for($comp)->create(['ticket_id' => $ticket->id, 'ticket_number' => null, 'announced_at' => now()->subHour()]);
    Winner::factory()->for($comp)->create(['ticket_number' => null, 'announced_at' => now()->subHours(2)]);

    expect($this->getJson('/api/v1/winners')->json('data.*.ticket'))->toBe(["#{$ticket->number}", 'live draw']);
});

it('uses the Europe/London date', function () {
    // 00:30 on 1 July in London is still 30 June in UTC.
    Winner::factory()->create(['announced_at' => CarbonImmutable::parse('2026-06-30 23:30:00', 'UTC')]);

    expect($this->getJson('/api/v1/winners')->json('data.0.date'))->toBe('01 Jul 2026');
});

it('lists the latest 50 announced winners, newest first', function () {
    $comp = Competition::factory()->drawn()->create();
    foreach (range(1, 55) as $i) {
        Winner::factory()->for($comp)->create(['prize_text' => "Prize {$i}", 'announced_at' => now()->subMinutes(60 - $i)]);
    }
    Winner::factory()->for($comp)->unannounced()->create(['prize_text' => 'Not yet']);
    Winner::factory()->for($comp)->create(['prize_text' => 'Scheduled', 'announced_at' => now()->addDay()]);

    $prizes = $this->getJson('/api/v1/winners')->json('data.*.prize');

    expect($prizes)->toHaveCount(50)
        ->and($prizes[0])->toBe('Prize 55')
        ->and($prizes[49])->toBe('Prize 6')
        ->and($prizes)->not->toContain('Not yet')->not->toContain('Scheduled');
});

it('busts the winners cache when a winner is announced', function () {
    expect($this->getJson('/api/v1/winners')->json('data'))->toBe([]);

    Winner::factory()->create();

    expect($this->getJson('/api/v1/winners')->json('data'))->toHaveCount(1);
});
