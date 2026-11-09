<?php

namespace Database\Seeders;

use App\Enums\Category;
use App\Enums\CompetitionKind;
use App\Enums\CompetitionStatus;
use App\Models\Competition;
use App\Models\Winner;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * The sample winners from web/src/data/comps.ts, each on a drawn comp of its own.
 */
class SampleWinnerSeeder extends Seeder
{
    public function run(): void
    {
        /** @var list<array{name: string, prize: string, ticket: int|null, announced: string}> $winners */
        $winners = require __DIR__.'/data/sample-winners.php';

        foreach ($winners as $data) {
            $slug = Str::slug(str_replace('£', '', $data['prize']));
            if (Competition::query()->where('slug', $slug)->exists()) {
                continue;
            }

            $announced = CarbonImmutable::parse($data['announced'], 'Europe/London')->utc();
            [$kind, $category] = match (true) {
                str_contains($data['prize'], 'mystery box') => [CompetitionKind::Box, Category::MysteryBoxes],
                str_contains($data['prize'], 'dartboard') => [CompetitionKind::Board, Category::Boards],
                default => [CompetitionKind::Darts, Category::Darts],
            };

            $comp = Competition::create([
                'slug' => $slug,
                'title' => $data['prize'],
                'brand' => Str::before($data['prize'], ' '),
                'kind' => $kind,
                'category' => $category,
                'description' => $data['prize'].'.',
                'status' => CompetitionStatus::Drawn,
                'price' => '0.99',
                'max_tickets' => 199,
                'draw_at' => $announced,
            ]);

            Winner::create([
                'competition_id' => $comp->id,
                'ticket_number' => $data['ticket'],
                'prize_text' => $data['prize'],
                'display_name' => $data['name'],
                'announced_at' => $announced,
            ]);
        }
    }
}
